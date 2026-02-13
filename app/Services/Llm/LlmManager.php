<?php

namespace App\Services\Llm;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Ai\AiManager as LaravelAiManager;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Responses\Data\Usage as LaravelAiUsage;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;

final class LlmManager
{
    /**
     * @param  string[]  $providers
     */
    public function __construct(
        private readonly LaravelAiManager $ai,
        private readonly LlmCostCalculator $costCalculator,
        private readonly array $providers,
        private readonly int $requestTimeout = 1800,
        private readonly int $anthropicMaxTokens = 64000,
    ) {}

    public function send(string $provider, LlmRequest $request): LlmResponse
    {
        if (! in_array($provider, $this->providers, true)) {
            throw new InvalidArgumentException(sprintf('LLM provider [%s] is not registered.', $provider));
        }

        $textProvider = $this->ai->textProvider($provider);
        $messages = $this->toLaravelMessages($request);
        $instructions = $this->instructions($request);
        $options = $this->generationOptions($request, $provider);
        $timeout = $this->timeoutSeconds();

        if ($request->shouldStream()) {
            $stream = $textProvider->textGateway()->streamText(
                invocationId: (string) Str::uuid7(),
                provider: $textProvider,
                model: $request->model,
                instructions: $instructions,
                messages: $messages,
                tools: [],
                schema: null,
                options: $options,
                timeout: $timeout,
            );

            return LlmResponse::streaming(function () use ($stream, $provider, $request) {
                foreach ($stream as $event) {
                    if ($event instanceof TextDelta) {
                        yield LlmResponseChunk::partial($event->delta, [
                            'provider' => $provider,
                            'model' => $request->model,
                        ]);

                        continue;
                    }

                    if ($event instanceof StreamEnd) {
                        $usage = $this->mapUsage(
                            provider: $provider,
                            model: $request->model,
                            usage: $event->usage,
                            inputType: $request->inputType(),
                        );

                        yield LlmResponseChunk::final('', $usage, [
                            'reason' => $event->reason,
                            'provider' => $provider,
                            'model' => $request->model,
                        ]);
                    }
                }
            });
        }

        $response = $textProvider->textGateway()->generateText(
            provider: $textProvider,
            model: $request->model,
            instructions: $instructions,
            messages: $messages,
            tools: [],
            schema: null,
            options: $options,
            timeout: $timeout,
        );

        $usage = $this->mapUsage(
            provider: $provider,
            model: $request->model,
            usage: $response->usage,
            inputType: $request->inputType(),
        );

        return LlmResponse::fromText(
            $response->text,
            $usage,
            [
                'provider' => $response->meta->provider ?? $provider,
                'model' => $response->meta->model ?? $request->model,
            ],
        );
    }

    /**
     * @return string[]
     */
    public function providers(): array
    {
        return $this->providers;
    }

    private function mapUsage(
        string $provider,
        string $model,
        LaravelAiUsage $usage,
        string $inputType,
    ): LlmUsage {
        $mapped = new LlmUsage(
            inputTokens: $usage->promptTokens,
            outputTokens: $usage->completionTokens,
            meta: [
                'provider' => $provider,
                'cache_write_input_tokens' => $usage->cacheWriteInputTokens,
                'cache_read_input_tokens' => $usage->cacheReadInputTokens,
                'reasoning_tokens' => $usage->reasoningTokens,
            ],
        );

        return $this->costCalculator->calculateUsageCost(
            provider: $provider,
            model: $model,
            usage: $mapped,
            inputType: $inputType,
        );
    }

    /**
     * @return list<Message>
     */
    private function toLaravelMessages(LlmRequest $request): array
    {
        return array_map(
            static fn (LlmMessage $message): Message => new Message($message->role, $message->content),
            $request->conversationMessages(),
        );
    }

    private function instructions(LlmRequest $request): ?string
    {
        $messages = array_filter(
            $request->messages,
            static fn (LlmMessage $message): bool => $message->role === LlmMessage::ROLE_SYSTEM,
        );

        $instructions = trim(implode(
            "\n\n",
            array_map(static fn (LlmMessage $message): string => $message->content, $messages),
        ));

        return $instructions !== '' ? $instructions : null;
    }

    private function generationOptions(LlmRequest $request, string $provider): ?TextGenerationOptions
    {
        $maxTokens = null;

        if (array_key_exists('max_tokens', $request->options)) {
            $maxTokens = (int) $request->options['max_tokens'];
        } elseif (array_key_exists('max_output_tokens', $request->options)) {
            $maxTokens = (int) $request->options['max_output_tokens'];
        } elseif ($provider === 'anthropic') {
            $maxTokens = $this->anthropicMaxTokens;
        }

        if ($maxTokens === null && $request->temperature === null) {
            return null;
        }

        return new TextGenerationOptions(
            maxTokens: $maxTokens,
            temperature: $request->temperature,
        );
    }

    private function timeoutSeconds(): int
    {
        return $this->requestTimeout;
    }
}
