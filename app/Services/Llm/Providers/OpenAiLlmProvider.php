<?php

namespace App\Services\Llm\Providers;

use App\Services\Llm\Contracts\LlmProvider;
use App\Services\Llm\LlmCostCalculator;
use App\Services\Llm\LlmMessage;
use App\Services\Llm\LlmRequest;
use App\Services\Llm\LlmResponse;
use App\Services\Llm\LlmResponseChunk;
use App\Services\Llm\LlmUsage;
use Generator;
use OpenAI\Contracts\ClientContract;
use OpenAI\Contracts\Resources\ChatContract;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Responses\Chat\CreateResponseChoice;
use OpenAI\Responses\Chat\CreateResponseUsage;
use OpenAI\Responses\Chat\CreateStreamedResponse;
use Traversable;

final class OpenAiLlmProvider implements LlmProvider
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
            'messages' => $this->formatMessages($request),
        ], $this->buildParameters($request));

        $chat = $this->client->chat();

        if ($request->shouldStream()) {
            /** @var Traversable<CreateStreamedResponse> $stream */
            $stream = $chat->createStreamed($payload);

            return LlmResponse::streaming(function () use ($stream, $request): Generator {
                $usage = null;
                $finishReason = null;

                foreach ($stream as $response) {
                    $currentUsage = $this->mapUsage($response->usage);
                    if ($currentUsage !== null) {
                        $usage = $this->costCalculator->calculateUsageCost(
                            provider: 'openai',
                            model: $request->model,
                            usage: $currentUsage,
                            inputType: $request->inputType(),
                        );
                    }

                    foreach ($response->choices as $choice) {
                        $finishReason = $choice->finishReason ?? $finishReason;
                        $delta = $choice->delta->content ?? '';

                        if ($delta !== '') {
                            yield LlmResponseChunk::partial($delta, [
                                'model' => $response->model,
                                'provider' => 'openai',
                            ]);
                        }
                    }
                }

                if ($usage !== null || $finishReason !== null) {
                    yield LlmResponseChunk::final('', $usage, ['finish_reason' => $finishReason]);
                }
            });
        }

        /** @var CreateResponse $response */
        $response = $chat->create($payload);

        $usage = $this->mapUsage($response->usage);
        if ($usage !== null) {
            $usage = $this->costCalculator->calculateUsageCost(
                provider: 'openai',
                model: $request->model,
                usage: $usage,
                inputType: $request->inputType(),
            );
        }

        return LlmResponse::fromText(
            $this->joinChoices($response->choices),
            $usage,
            ['finish_reason' => $response->choices[0]->finishReason ?? null],
        );
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function formatMessages(LlmRequest $request): array
    {
        $messages = [];

        foreach ($request->messages as $message) {
            $messages[] = $message->toArray();
        }

        return $messages;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildParameters(LlmRequest $request): array
    {
        $params = $request->options;

        if ($request->temperature !== null) {
            $params['temperature'] = $request->temperature;
        }

        return $params;
    }

    /**
     * @param  array<int, CreateResponseChoice>  $choices
     */
    private function joinChoices(array $choices): string
    {
        $buffer = '';

        foreach ($choices as $choice) {
            $buffer .= $choice->message->content ?? '';
        }

        return $buffer;
    }

    private function mapUsage(?CreateResponseUsage $usage): ?LlmUsage
    {
        if ($usage === null) {
            return null;
        }

        return new LlmUsage(
            inputTokens: $usage->promptTokens,
            outputTokens: $usage->completionTokens ?? 0,
            meta: [
                'total_tokens' => $usage->totalTokens,
                'provider' => 'openai',
            ],
        );
    }
}
