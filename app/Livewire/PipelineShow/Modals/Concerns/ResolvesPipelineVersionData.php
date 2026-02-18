<?php

namespace App\Livewire\PipelineShow\Modals\Concerns;

use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\StepVersion;
use App\Services\Pipeline\PipelineDetailsQuery;

trait ResolvesPipelineVersionData
{
    public Pipeline $pipeline;

    public int $pipelineId;

    public ?int $selectedVersionId = null;

    protected function refreshPipelineData(): void
    {
        $pipeline = Pipeline::query()->findOrFail($this->pipelineId);
        $this->pipeline = app(PipelineDetailsQuery::class)->get($pipeline);
    }

    protected function resolveSelectedVersion(): ?PipelineVersion
    {
        return $this->pipeline->versions
            ->first(fn (PipelineVersion $version): bool => (int) $version->id === (int) $this->selectedVersionId);
    }

    /**
     * @return array<int, array{
     *     position:int,
     *     step_version: StepVersion,
     *     input_step_name: string|null,
     *     model_label: string
     * }>
     */
    protected function resolveSelectedVersionSteps(): array
    {
        $selectedVersion = $this->resolveSelectedVersion();

        if ($selectedVersion === null) {
            return [];
        }

        $stepNameByStepId = $selectedVersion->versionSteps
            ->mapWithKeys(fn ($versionStep): array => [
                (int) ($versionStep->stepVersion?->step_id ?? 0) => $versionStep->stepVersion?->name,
            ]);

        return $selectedVersion->versionSteps
            ->map(function ($versionStep) use ($stepNameByStepId): ?array {
                $stepVersion = $versionStep->stepVersion;

                if ($stepVersion === null) {
                    return null;
                }

                $inputStepName = null;

                if ($stepVersion->input_step_id !== null) {
                    $inputStepName = $stepNameByStepId->get((int) $stepVersion->input_step_id)
                        ?? $stepVersion->inputStepCurrentVersion()?->name;
                }

                return [
                    'position' => (int) $versionStep->position,
                    'step_version' => $stepVersion,
                    'input_step_name' => $inputStepName,
                    'model_label' => $this->stepModelLabel($stepVersion),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function resolveSelectedVersionIsCurrent(): bool
    {
        $selectedVersionId = (int) ($this->resolveSelectedVersion()?->id ?? 0);
        $currentVersionId = (int) ($this->pipeline->current_version_id ?? 0);

        return $selectedVersionId !== 0 && $selectedVersionId === $currentVersionId;
    }

    private function stepModelLabel(StepVersion $stepVersion): string
    {
        $model = trim((string) data_get($stepVersion->settings, 'model', ''));

        return $model !== '' ? $model : 'Модель не указана';
    }
}
