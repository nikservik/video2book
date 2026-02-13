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
use Laravel\Ai\Files\Audio;
use Laravel\Ai\Responses\TranscriptionResponse;
use Laravel\Ai\Transcription;
use RuntimeException;
use Throwable;
use function Laravel\Ai\agent;

final class DefaultPipelineStepExecutor implements PipelineStepExecutor
{
    public function __construct(
        private readonly LlmManager $llmManager,
        private readonly LlmCostCalculator $costCalculator,
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

        $requestedProvider = (string) Arr::get($version->settings, 'provider', 'openai');
        $provider = $requestedProvider;
        $model = $this->normalizeTranscriptionModel($provider, Arr::get($version->settings, 'model'));
        $language = Arr::get($version->settings, 'language');
        $filePath = $disk->path($lesson->source_filename);
        $prompt = trim((string) $version->prompt);

        // Для Gemini транскрибации используем текстовый multimodal вызов с audio attachment.
        if ($provider === 'gemini' && ! $this->isWhisperModel($model)) {
            return $this->transcribeWithGemini(
                filePath: $filePath,
                model: $model ?? 'gemini-3-flash-preview',
                prompt: $prompt,
                requestedProvider: $requestedProvider,
            );
        }

        // Для Whisper всегда используем стандартный STT из Laravel AI SDK.
        if ($this->isWhisperModel($model)) {
            $provider = 'openai';
        }

        $pending = Transcription::fromPath($filePath, $this->detectAudioMime($filePath));

        if (is_string($language) && $language !== '') {
            $pending->language($language);
        }

        $response = $pending->generate($provider, $model);

        $usage = $this->mapTranscriptionUsage(
            provider: $provider,
            model: $model,
            response: $response,
        );

        return new PipelineStepResult(
            output: $response->text,
            inputTokens: $usage?->inputTokens,
            outputTokens: $usage?->outputTokens,
            cost: $usage?->cost,
            meta: array_filter([
                'provider' => $provider,
                'provider_requested' => $requestedProvider,
                'model' => $response->meta->model ?? $model,
                'segments' => $response->segments->count(),
            ], static fn (mixed $value): bool => $value !== null),
        );
    }

    private function handleTextualStep(PipelineRun $run, StepVersion $version, ?string $input): PipelineStepResult
    {
        if ($input === null || $input === '') {
            throw new RuntimeException('Входные данные для текстового шага отсутствуют.');
        }

        $settings = $version->settings ?? [];
        $provider = (string) ($settings['provider'] ?? 'openai');
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
            // Совместимость со старыми названиями.
            if ($model === 'gemini-3-flash' || $model === 'gemini-3.0-flash') {
                return 'gemini-3-flash-preview';
            }
        }

        return $model;
    }

    private function normalizeTranscriptionModel(string $provider, ?string $model): ?string
    {
        if ($provider === 'gemini') {
            if ($model === null || $model === '') {
                return 'gemini-3-flash-preview';
            }

            if ($model === 'gemini-3-flash' || $model === 'gemini-3.0-flash') {
                return 'gemini-3-flash-preview';
            }

            return $model;
        }

        if ($model === null || $model === '') {
            return 'whisper-1';
        }

        if (str_starts_with($model, 'gemini-')) {
            return 'whisper-1';
        }

        return $model;
    }

    private function transcribeWithGemini(
        string $filePath,
        string $model,
        string $prompt,
        string $requestedProvider,
    ): PipelineStepResult {
        $instruction = $prompt !== ''
            ? $prompt
            : 'Transcribe this audio accurately in the source language. Preserve punctuation and paragraph breaks.';

        $response = agent()->prompt(
            prompt: $instruction,
            attachments: [Audio::fromPath($filePath, $this->detectAudioMime($filePath))],
            provider: 'gemini',
            model: $model,
            timeout: (int) config('llm.request_timeout', 1800),
        );

        $usage = $this->mapGeminiTextUsage($response->usage->promptTokens, $response->usage->completionTokens, $model);

        return new PipelineStepResult(
            output: $response->text,
            inputTokens: $usage?->inputTokens,
            outputTokens: $usage?->outputTokens,
            cost: $usage?->cost,
            meta: array_filter([
                'provider' => 'gemini',
                'provider_requested' => $requestedProvider,
                'model' => $response->meta->model ?? $model,
            ], static fn (mixed $value): bool => $value !== null),
        );
    }

    private function mapGeminiTextUsage(int $inputTokens, int $outputTokens, string $model): ?LlmUsage
    {
        if ($inputTokens === 0 && $outputTokens === 0) {
            return null;
        }

        return $this->costCalculator->calculateUsageCost(
            provider: 'gemini',
            model: $model,
            usage: new LlmUsage(
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                meta: [
                    'provider' => 'gemini',
                ],
            ),
            inputType: LlmRequest::INPUT_TYPE_AUDIO,
        );
    }

    private function isWhisperModel(?string $model): bool
    {
        if (! is_string($model) || $model === '') {
            return false;
        }

        return str_starts_with(strtolower($model), 'whisper');
    }

    private function detectAudioMime(string $filePath): string
    {
        return match (strtolower(pathinfo($filePath, PATHINFO_EXTENSION))) {
            'wav' => 'audio/wav',
            'aiff' => 'audio/aiff',
            'aac' => 'audio/aac',
            'ogg' => 'audio/ogg',
            'flac' => 'audio/flac',
            default => 'audio/mpeg',
        };
    }

    private function mapTranscriptionUsage(string $provider, ?string $model, TranscriptionResponse $response): ?LlmUsage
    {
        $usage = $response->usage;

        if ($usage->promptTokens === 0 && $usage->completionTokens === 0) {
            return null;
        }

        $resolvedModel = $response->meta->model ?? $model;

        if (! is_string($resolvedModel) || $resolvedModel === '') {
            return new LlmUsage(
                inputTokens: $usage->promptTokens,
                outputTokens: $usage->completionTokens,
                meta: [
                    'provider' => $provider,
                ],
            );
        }

        return $this->costCalculator->calculateUsageCost(
            provider: $provider,
            model: $resolvedModel,
            usage: new LlmUsage(
                inputTokens: $usage->promptTokens,
                outputTokens: $usage->completionTokens,
                meta: [
                    'provider' => $provider,
                ],
            ),
            inputType: LlmRequest::INPUT_TYPE_AUDIO,
        );
    }
}
