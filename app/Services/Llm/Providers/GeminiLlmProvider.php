<?php

namespace App\Services\Llm\Providers;

use App\Services\Llm\Contracts\LlmProvider;
use App\Services\Llm\LlmCostCalculator;
use App\Services\Llm\LlmMessage;
use App\Services\Llm\LlmRequest;
use App\Services\Llm\LlmResponse;
use App\Services\Llm\LlmResponseChunk;
use App\Services\Llm\LlmUsage;
use Gemini\Contracts\ClientContract;
use Gemini\Data\Content;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\Role;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Gemini\Responses\StreamResponse as GeminiStreamResponse;
use Generator;

final class GeminiLlmProvider implements LlmProvider
{
    public function __construct(
        private readonly ClientContract $client,
        private readonly LlmCostCalculator $costCalculator,
    ) {
    }

    public function send(LlmRequest $request): LlmResponse
    {
        $model = $this->client->generativeModel($request->model);

        if ($system = $request->systemMessage()?->content) {
            $model = $model->withSystemInstruction(Content::parse($system, Role::USER));
        }

        if ($config = $this->buildGenerationConfig($request)) {
            $model = $model->withGenerationConfig($config);
        }

        $contents = $this->formatMessages($request);

        if ($request->shouldStream()) {
            /** @var GeminiStreamResponse<GenerateContentResponse> $stream */
            $stream = $model->streamGenerateContent(...$contents);

            return LlmResponse::streaming(function () use ($stream, $request): Generator {
                $usage = null;

                foreach ($stream as $response) {
                    $currentUsage = $this->mapUsage($response);
                    if ($currentUsage !== null) {
                        $usage = $this->costCalculator->calculateUsageCost(
                            provider: 'gemini',
                            model: $request->model,
                            usage: $currentUsage,
                            inputType: $request->inputType(),
                        );
                    }
                    $text = $this->extractText($response);

                    if ($text !== '') {
                        yield LlmResponseChunk::partial($text, ['provider' => 'gemini']);
                    }
                }

                if ($usage !== null) {
                    yield LlmResponseChunk::final('', $usage);
                }
            });
        }

        $response = $model->generateContent(...$contents);

        $usage = $this->mapUsage($response);
        if ($usage !== null) {
            $usage = $this->costCalculator->calculateUsageCost(
                provider: 'gemini',
                model: $request->model,
                usage: $usage,
                inputType: $request->inputType(),
            );
        }

        return LlmResponse::fromText(
            $this->extractText($response),
            $usage,
        );
    }

    /**
     * @return array<int, Content>
     */
    private function formatMessages(LlmRequest $request): array
    {
        $messages = $request->conversationMessages();

        if ($messages === []) {
            return [Content::parse('', Role::USER)];
        }

        return array_map(function (LlmMessage $message): Content {
            $role = $message->role === LlmMessage::ROLE_ASSISTANT ? Role::MODEL : Role::USER;

            return Content::parse($message->content, $role);
        }, $messages);
    }

    private function buildGenerationConfig(LlmRequest $request): ?GenerationConfig
    {
        $maxOutput = $request->options['max_output_tokens'] ?? null;
        $topP = $request->options['top_p'] ?? null;
        $topK = $request->options['top_k'] ?? null;

        if ($request->temperature === null && $maxOutput === null && $topP === null && $topK === null) {
            return null;
        }

        return new GenerationConfig(
            temperature: $request->temperature,
            maxOutputTokens: $maxOutput,
            topP: $topP,
            topK: $topK,
        );
    }

    private function extractText(GenerateContentResponse $response): string
    {
        $buffer = '';

        foreach ($response->candidates as $candidate) {
            foreach ($candidate->content->parts as $part) {
                if ($part->text !== null) {
                    $buffer .= $part->text;
                }
            }
        }

        return $buffer;
    }

    private function mapUsage(GenerateContentResponse $response): ?LlmUsage
    {
        $usage = $response->usageMetadata;

        if ($usage === null) {
            return null;
        }

        $candidatesTokens = $usage->candidatesTokenCount ?? max(0, $usage->totalTokenCount - $usage->promptTokenCount);

        return new LlmUsage(
            inputTokens: $usage->promptTokenCount,
            outputTokens: $candidatesTokens,
            meta: [
                'total_tokens' => $usage->totalTokenCount,
                'provider' => 'gemini',
            ],
        );
    }
}
