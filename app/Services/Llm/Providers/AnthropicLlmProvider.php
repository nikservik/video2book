<?php

namespace App\Services\Llm\Providers;

use Anthropic\Contracts\ClientContract;
use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Messages\CreateResponseContent;
use Anthropic\Responses\Messages\CreateResponseUsage;
use Anthropic\Responses\Messages\CreateStreamedResponse;
use App\Services\Llm\Contracts\LlmProvider;
use App\Services\Llm\LlmCostCalculator;
use App\Services\Llm\LlmMessage;
use App\Services\Llm\LlmRequest;
use App\Services\Llm\LlmResponse;
use App\Services\Llm\LlmResponseChunk;
use App\Services\Llm\LlmUsage;
use Generator;
use Traversable;

final class AnthropicLlmProvider implements LlmProvider
{
    public function __construct(
        private readonly ClientContract $client,
        private readonly LlmCostCalculator $costCalculator,
    ) {
    }

    public function send(LlmRequest $request): LlmResponse
    {
        $payload = array_merge([
            'model' => $request->model,
            'system' => $request->systemMessage()?->content,
            'messages' => $this->formatMessages($request),
            'max_tokens' => $request->options['max_tokens'] ?? 4096,
        ], $this->buildOptions($request));

        $messages = $this->client->messages();

        if ($request->shouldStream()) {
            /** @var Traversable<CreateStreamedResponse> $stream */
            $stream = $messages->createStreamed($payload);

            return LlmResponse::streaming(function () use ($stream, $request): Generator {
                $usage = null;

                foreach ($stream as $response) {
                    $streamUsage = $this->mapStreamUsage($response);
                    if ($streamUsage !== null) {
                        $usage = $this->costCalculator->calculateUsageCost(
                            provider: 'anthropic',
                            model: $request->model,
                            usage: $streamUsage,
                            inputType: $request->inputType(),
                        );
                    }
                    $delta = $response->delta->text ?? '';

                    if ($delta !== '') {
                        yield LlmResponseChunk::partial($delta, ['provider' => 'anthropic']);
                    }
                }

                if ($usage !== null) {
                    yield LlmResponseChunk::final('', $usage);
                }
            });
        }

        /** @var CreateResponse $response */
        $response = $messages->create($payload);

        $usage = $this->mapUsage($response->usage);
        if ($usage !== null) {
            $usage = $this->costCalculator->calculateUsageCost(
                provider: 'anthropic',
                model: $request->model,
                usage: $usage,
                inputType: $request->inputType(),
            );
        }

        return LlmResponse::fromText(
            $this->extractText($response->content),
            $usage,
            ['stop_reason' => $response->stop_reason],
        );
    }

    /**
     * @return array<int, array{role: string, content: array<int, array{type: string, text: string}>}>
     */
    private function formatMessages(LlmRequest $request): array
    {
        $messages = [];

        foreach ($request->conversationMessages() as $message) {
            $role = $message->role === LlmMessage::ROLE_ASSISTANT ? 'assistant' : 'user';

            $messages[] = [
                'role' => $role,
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $message->content,
                    ],
                ],
            ];
        }

        return $messages;
    }

    /**
     * @param  array<int, CreateResponseContent>  $content
     */
    private function extractText(array $content): string
    {
        $buffer = '';

        foreach ($content as $entry) {
            if ($entry->text !== null) {
                $buffer .= $entry->text;
            }
        }

        return $buffer;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOptions(LlmRequest $request): array
    {
        $options = $request->options;

        if ($request->temperature !== null) {
            $options['temperature'] = $request->temperature;
        }

        return $options;
    }

    private function mapUsage(?CreateResponseUsage $usage): ?LlmUsage
    {
        if ($usage === null) {
            return null;
        }

        return new LlmUsage(
            inputTokens: $usage->inputTokens,
            outputTokens: $usage->outputTokens,
            meta: [
                'cache_creation_tokens' => $usage->cacheCreationInputTokens,
                'cache_read_tokens' => $usage->cacheReadInputTokens,
                'provider' => 'anthropic',
            ],
        );
    }

    private function mapStreamUsage(CreateStreamedResponse $response): ?LlmUsage
    {
        $usage = $response->usage;

        if ($usage->inputTokens === null && $usage->outputTokens === null) {
            return null;
        }

        return new LlmUsage(
            inputTokens: $usage->inputTokens ?? 0,
            outputTokens: $usage->outputTokens ?? 0,
            meta: ['provider' => 'anthropic'],
        );
    }
}
