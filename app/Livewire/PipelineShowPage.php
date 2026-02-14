<?php

namespace App\Livewire;

use App\Actions\Pipeline\TogglePipelineVersionArchiveStatusAction;
use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\StepVersion;
use App\Services\Pipeline\PipelineDetailsQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class PipelineShowPage extends Component
{
    public Pipeline $pipeline;

    public ?int $selectedVersionId = null;

    public function mount(Pipeline $pipeline): void
    {
        $this->pipeline = app(PipelineDetailsQuery::class)->get($pipeline);
        $this->selectedVersionId = $this->pipeline->current_version_id ?? $this->pipeline->versions->first()?->id;
    }

    public function selectVersion(int $versionId): void
    {
        $versionExists = $this->pipeline->versions
            ->contains(fn (PipelineVersion $version): bool => $version->id === $versionId);

        if (! $versionExists) {
            return;
        }

        $this->selectedVersionId = $versionId;
    }

    public function toggleSelectedVersionArchiveStatus(TogglePipelineVersionArchiveStatusAction $action): void
    {
        $selectedVersion = $this->selectedVersion;

        if ($selectedVersion === null) {
            return;
        }

        $action->handle($selectedVersion);

        $this->pipeline = app(PipelineDetailsQuery::class)->get($this->pipeline);
    }

    public function getSelectedVersionProperty(): ?PipelineVersion
    {
        return $this->pipeline->versions
            ->first(fn (PipelineVersion $version): bool => $version->id === $this->selectedVersionId);
    }

    /**
     * @return array<int, array{
     *     position:int,
     *     step_version: StepVersion,
     *     input_step_name: string|null,
     *     model_label: string
     * }>
     */
    public function getSelectedVersionStepsProperty(): array
    {
        $selectedVersion = $this->selectedVersion;

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

    public function getSelectedVersionTitleProperty(): string
    {
        return $this->selectedVersion?->title ?? 'Без названия';
    }

    public function getSelectedVersionNumberProperty(): string
    {
        return (string) ($this->selectedVersion?->version ?? '—');
    }

    public function getSelectedVersionIsArchivedProperty(): bool
    {
        return $this->selectedVersion?->status === 'archived';
    }

    /**
     * @return \Illuminate\Support\Collection<int, PipelineVersion>
     */
    public function getPipelineVersionsProperty(): Collection
    {
        return $this->pipeline->versions
            ->sortByDesc('version')
            ->values();
    }

    private function stepModelLabel(StepVersion $stepVersion): string
    {
        $model = trim((string) data_get($stepVersion->settings, 'model', ''));

        return $model !== '' ? $model : 'Модель не указана';
    }

    public function render(): View
    {
        return view('pages.pipeline-show-page', [
            'pipeline' => $this->pipeline,
            'selectedVersion' => $this->selectedVersion,
            'selectedVersionSteps' => $this->selectedVersionSteps,
        ])->layout('layouts.app', [
            'title' => 'Пайплайн | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [
                ['label' => 'Пайплайны', 'url' => route('pipelines.index')],
                ['label' => $this->selectedVersionTitle, 'current' => true],
            ],
        ]);
    }
}
