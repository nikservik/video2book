<?php

namespace App\Services\Pipeline;

use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\StepVersion;
use App\Services\Llm\AnthropicRateLimiter;
use App\Services\Llm\Exceptions\HaikuRateLimitExceededException;
use App\Services\Llm\LlmCostCalculator;
use App\Services\Llm\LlmManager;
use App\Services\Llm\LlmMessage;
use App\Services\Llm\LlmRequest;
use App\Services\Llm\LlmUsage;
use App\Services\Pipeline\Contracts\PipelineStepExecutor;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use OpenAI\Contracts\ClientContract;
use RuntimeException;
use Throwable;
use Gemini\Contracts\ClientContract as GeminiClientContract;
use Gemini\Data\Blob;
use Gemini\Data\Content;
use Gemini\Enums\MimeType;
use Gemini\Enums\Role;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;

final class DefaultPipelineStepExecutor implements PipelineStepExecutor
{
    public function __construct(
        private readonly LlmManager $llmManager,
        private readonly LlmCostCalculator $costCalculator,
        private readonly ClientContract $openAIClient,
        private readonly GeminiClientContract $geminiClient,
        private readonly AnthropicRateLimiter $haikuLimiter,
    ) {}

    public function execute(PipelineRun $run, PipelineRunStep $step, ?string $input): PipelineStepResult
    {
        $stepVersion = $step->stepVersion;

        if ($stepVersion === null) {
            throw new RuntimeException('Версия шага не загружена.');
        }

        return match ($stepVersion->type) {
            'transcribe' => $this->handleTranscription($run, $stepVersion),
            'text', 'glossary' => $this->handleTextualStep($run, $stepVersion, $input),
            default => throw new RuntimeException(sprintf('Неизвестный тип шага: %s', $stepVersion->type)),
        };
    }

    private function handleTranscription(PipelineRun $run, StepVersion $version): PipelineStepResult
    {
        $lesson = $run->lesson;

        if ($lesson === null || empty($lesson->source_filename)) {
            throw new RuntimeException('Для урока не загружен нормализованный аудио-файл.');
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($lesson->source_filename)) {
            throw new RuntimeException('Нормализованный аудио-файл отсутствует в хранилище.');
        }

        $provider = Arr::get($version->settings, 'provider', 'openai');
        $model = Arr::get($version->settings, 'model');
        $filePath = $disk->path($lesson->source_filename);

        if ($provider === 'gemini') {
            $model = $this->normalizeModel('gemini', $model) ?? 'gemini-3-flash-preview';

            return $this->transcribeWithGemini($filePath, $model, $version->prompt);
        }

        $model = $this->normalizeTranscriptionModel($model);

        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Не удалось открыть аудио-файл для чтения.');
        }

        $payload = array_filter([
            'model' => $model,
            'file' => $handle,
            'response_format' => 'text',
            'temperature' => Arr::get($version->settings, 'temperature'),
            'language' => Arr::get($version->settings, 'language'),
            'prompt' => $version->prompt,
        ], static fn ($value) => $value !== null);

        try {
            $response = $this->openAIClient->audio()->transcribe($payload);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        $minutes = $response->duration !== null ? $response->duration / 60 : null;
        $cost = $minutes !== null ? $this->costCalculator->whisper($minutes) : null;

        return new PipelineStepResult(
            output: $response->text,
            cost: $cost,
            meta: array_filter([
                'language' => $response->language,
                'duration' => $response->duration,
            ], static fn ($value) => $value !== null),
        );
    }

    private function handleTextualStep(PipelineRun $run, StepVersion $version, ?string $input): PipelineStepResult
    {
        if ($input === null || $input === '') {
            throw new RuntimeException('Входные данные для текстового шага отсутствуют.');
        }

        $settings = $version->settings ?? [];
        $provider = $settings['provider'] ?? 'openai';
        $model = $this->normalizeModel($provider, $settings['model'] ?? null);

        if ($model === null) {
            throw new RuntimeException('Для шага не указана модель LLM.');
        }

        $temperature = array_key_exists('temperature', $settings)
            ? (float) $settings['temperature']
            : null;

        $messages = [];

        if (! empty($version->prompt)) {
            $messages[] = LlmMessage::system($version->prompt);
        }

        $messages[] = LlmMessage::user($input);

        $reservationId = null;
        $estimatedOutputTokens = null;

        if ($provider === 'anthropic' && str_contains(strtolower($model), 'haiku')) {
            $estimatedOutputTokens = $this->haikuLimiter->estimateOutputTokens($input);
            $reservation = $this->haikuLimiter->reserve($estimatedOutputTokens);

            if (! $reservation->allowed || $reservation->reservationId === null) {
                throw new HaikuRateLimitExceededException($reservation->retryAfterSeconds);
            }

            $reservationId = $reservation->reservationId;
        }

        try {
            $response = $this->llmManager->send(
                $provider,
                new LlmRequest(
                    model: $model,
                    messages: $messages,
                    temperature: $temperature,
                    stream: false,
                )
            );

            $content = $response->collect();

            /** @var LlmUsage|null $usage */
            $usage = $response->usage();
        } catch (Throwable $e) {
            if ($reservationId !== null) {
                $this->haikuLimiter->release($reservationId);
            }

            throw $e;
        }

        if ($reservationId !== null) {
            $this->haikuLimiter->finalize(
                $reservationId,
                $usage?->outputTokens ?? $estimatedOutputTokens ?? 0
            );
        }

        return new PipelineStepResult(
            output: $content,
            inputTokens: $usage?->inputTokens,
            outputTokens: $usage?->outputTokens,
            cost: $usage?->cost,
            meta: $usage?->meta ?? [],
        );
    }

    private function normalizeModel(string $provider, ?string $model): ?string
    {
        if ($model === null) {
            return null;
        }

        if ($provider === 'gemini') {
            // совместимость со старыми названиями
            if ($model === 'gemini-3-flash') {
                return 'gemini-3-flash-preview';
            }

            if ($model === 'gemini-3.0-flash') {
                return 'gemini-3-flash-preview';
            }

            if (str_starts_with($model, 'models/')) {
                return substr($model, strlen('models/'));
            }
        }

        return $model;
    }

    private function normalizeTranscriptionModel(?string $model): string
    {
        if (is_string($model) && str_starts_with($model, 'whisper')) {
            return $model;
        }

        return 'whisper-1';
    }

    private function transcribeWithGemini(string $filePath, string $model, ?string $prompt): PipelineStepResult
    {
        $audio = file_get_contents($filePath);

        if ($audio === false) {
            throw new RuntimeException('Не удалось прочитать аудио-файл для транскрибации.');
        }

        $mimeType = $this->detectAudioMime($filePath);
        $blob = new Blob($mimeType, base64_encode($audio));

        $instruction = trim((string) $prompt);
        $parts = [$blob];

        if ($instruction !== '') {
            $parts[] = $instruction;
        } else {
            $parts[] = 'Transcribe this audio to text in the original language. Keep paragraphs and punctuation.';
        }

        $response = $this->geminiClient
            ->generativeModel($model)
            ->generateContent(
                Content::parse($parts, Role::USER)
            );

        $text = $this->extractGeminiText($response);
        $usage = $this->mapGeminiUsage($response);

        if ($usage !== null) {
            $usage = $this->costCalculator->calculateUsageCost(
                provider: 'gemini',
                model: $model,
                usage: $usage,
                inputType: LlmRequest::INPUT_TYPE_AUDIO,
            );
        }

        return new PipelineStepResult(
            output: $text,
            inputTokens: $usage?->inputTokens,
            outputTokens: $usage?->outputTokens,
            cost: $usage?->cost,
            meta: $usage?->meta ?? [],
        );
    }

    private function detectAudioMime(string $filePath): MimeType
    {
        return match (strtolower(pathinfo($filePath, PATHINFO_EXTENSION))) {
            'wav' => MimeType::AUDIO_WAV,
            'aiff' => MimeType::AUDIO_AIFF,
            'aac' => MimeType::AUDIO_AAC,
            'ogg' => MimeType::AUDIO_OGG,
            'flac' => MimeType::AUDIO_FLAC,
            default => MimeType::AUDIO_MP3,
        };
    }

    private function extractGeminiText(GenerateContentResponse $response): string
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

    private function mapGeminiUsage(GenerateContentResponse $response): ?LlmUsage
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
