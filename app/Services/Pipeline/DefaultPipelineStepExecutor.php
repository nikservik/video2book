<?php

namespace App\Services\Pipeline;

use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\StepVersion;
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

final class DefaultPipelineStepExecutor implements PipelineStepExecutor
{
    public function __construct(
        private readonly LlmManager $llmManager,
        private readonly LlmCostCalculator $costCalculator,
        private readonly ClientContract $openAIClient,
    ) {}

    public function execute(PipelineRun $run, PipelineRunStep $step, ?string $input): PipelineStepResult
    {
        $stepVersion = $step->stepVersion;

        if ($stepVersion === null) {
            throw new RuntimeException('Версия шага не загружена.');
        }

        return match ($stepVersion->type) {
            'transcribe' => $this->handleTranscription($run, $stepVersion),
            'text', 'glossary' => $this->handleTextualStep($stepVersion, $input),
            default => throw new RuntimeException(sprintf('Неизвестный тип шага: %s', $stepVersion->type)),
        };
    }

    private function handleTranscription(PipelineRun $run, StepVersion $version): PipelineStepResult
    {
        $project = $run->project;

        if ($project === null || empty($project->source_filename)) {
            throw new RuntimeException('Для проекта не загружен нормализованный аудио-файл.');
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($project->source_filename)) {
            throw new RuntimeException('Нормализованный аудио-файл отсутствует в хранилище.');
        }

        $model = Arr::get($version->settings, 'model', 'whisper-1');
        $filePath = $disk->path($project->source_filename);

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
            fclose($handle);
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

    private function handleTextualStep(StepVersion $version, ?string $input): PipelineStepResult
    {
        if ($input === null || $input === '') {
            throw new RuntimeException('Входные данные для текстового шага отсутствуют.');
        }

        $settings = $version->settings ?? [];
        $provider = $settings['provider'] ?? 'openai';
        $model = $settings['model'] ?? null;

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

        return new PipelineStepResult(
            output: $content,
            inputTokens: $usage?->inputTokens,
            outputTokens: $usage?->outputTokens,
            cost: $usage?->cost,
            meta: $usage?->meta ?? [],
        );
    }
}
