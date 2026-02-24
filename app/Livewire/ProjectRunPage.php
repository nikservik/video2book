<?php

namespace App\Livewire;

use App\Actions\Pipeline\PausePipelineRunAction;
use App\Actions\Pipeline\RestartPipelineRunFromStepAction;
use App\Actions\Pipeline\StartPipelineRunAction;
use App\Actions\Pipeline\StopPipelineRunAction;
use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\Project;
use App\Models\User;
use App\Services\Pipeline\PipelineStepDocxExporter;
use App\Services\Pipeline\PipelineStepPdfExporter;
use App\Services\Project\ProjectRunDetailsQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectRunPage extends Component
{
    public Project $project;

    public PipelineRun $pipelineRun;

    public ?int $selectedStepId = null;

    public bool $isZeroAccessLevelUser = false;

    public function mount(Project $project, PipelineRun $pipelineRun): void
    {
        $this->pipelineRun = app(ProjectRunDetailsQuery::class)->get($pipelineRun);

        abort_unless(
            $this->pipelineRun->lesson?->project_id === $project->id,
            404
        );

        $this->project = $project;

        $authUser = auth()->user();
        $this->isZeroAccessLevelUser = $authUser instanceof User
            && (int) $authUser->access_level === User::ACCESS_LEVEL_USER;
        $this->selectedStepId = $this->resolveInitialSelectedStepId();
    }

    public function selectStep(int $pipelineRunStepId): void
    {
        abort_unless($this->pipelineRun->steps->contains('id', $pipelineRunStepId), 404);

        $this->selectedStepId = $pipelineRunStepId;
    }

    public function startRun(StartPipelineRunAction $startPipelineRunAction): void
    {
        $this->pipelineRun = app(ProjectRunDetailsQuery::class)->get(
            $startPipelineRunAction->handle($this->pipelineRun)
        );
    }

    public function pauseRun(PausePipelineRunAction $pausePipelineRunAction): void
    {
        $this->pipelineRun = app(ProjectRunDetailsQuery::class)->get(
            $pausePipelineRunAction->handle($this->pipelineRun)
        );
    }

    public function stopRun(StopPipelineRunAction $stopPipelineRunAction): void
    {
        $this->pipelineRun = app(ProjectRunDetailsQuery::class)->get(
            $stopPipelineRunAction->handle($this->pipelineRun)
        );
    }

    public function restartSelectedStep(RestartPipelineRunFromStepAction $restartPipelineRunFromStepAction): void
    {
        abort_if($this->selectedStep === null, 422, 'Шаг для перезапуска не выбран.');

        $this->pipelineRun = app(ProjectRunDetailsQuery::class)->get(
            $restartPipelineRunFromStepAction->handle($this->pipelineRun, $this->selectedStep)
        );
    }

    public function refreshRunControls(): void
    {
        $this->pipelineRun = app(ProjectRunDetailsQuery::class)->get($this->pipelineRun->fresh());
    }

    public function refreshRunSteps(): void
    {
        $this->pipelineRun = app(ProjectRunDetailsQuery::class)->get($this->pipelineRun->fresh());

        if ($this->selectedStepId !== null && $this->pipelineRun->steps->contains('id', $this->selectedStepId)) {
            return;
        }

        $this->selectedStepId = $this->resolveInitialSelectedStepId();
    }

    public function refreshSelectedStepResult(): void
    {
        $this->pipelineRun = app(ProjectRunDetailsQuery::class)->get($this->pipelineRun->fresh());

        if ($this->selectedStepId !== null && $this->pipelineRun->steps->contains('id', $this->selectedStepId)) {
            return;
        }

        $this->selectedStepId = $this->resolveInitialSelectedStepId();
    }

    public function downloadSelectedStepPdf(PipelineStepPdfExporter $exporter): StreamedResponse
    {
        $step = $this->selectedStepForExport();
        $filename = $this->selectedStepExportFilename($step, 'pdf');
        $pdfContent = $exporter->export($this->pipelineRun, $step);

        return response()->streamDownload(function () use ($pdfContent): void {
            echo $pdfContent;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function downloadSelectedStepMarkdown(): StreamedResponse
    {
        $step = $this->selectedStepForExport();
        $filename = $this->selectedStepExportFilename($step, 'md');

        return response()->streamDownload(function () use ($step): void {
            echo $step->result;
        }, $filename, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
        ]);
    }

    public function downloadSelectedStepDocx(PipelineStepDocxExporter $exporter): StreamedResponse
    {
        $step = $this->selectedStepForExport();
        $filename = $this->selectedStepExportFilename($step, 'docx');
        $docxContent = $exporter->export($this->pipelineRun, $step);

        return response()->streamDownload(function () use ($docxContent): void {
            echo $docxContent;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function getSelectedStepProperty(): ?PipelineRunStep
    {
        return $this->pipelineRun->steps->firstWhere('id', $this->selectedStepId);
    }

    public function getSelectedStepNumberProperty(): ?int
    {
        if ($this->selectedStep === null) {
            return null;
        }

        $stepIndex = $this->pipelineRun->steps->search(
            fn (PipelineRunStep $step): bool => $step->id === $this->selectedStep->id
        );

        if ($stepIndex === false) {
            return null;
        }

        return $stepIndex + 1;
    }

    public function getSelectedStepResultProperty(): string
    {
        if ($this->selectedStep === null) {
            return 'Шаги прогона пока не добавлены.';
        }

        return (string) ($this->selectedStep->result ?? '');
    }

    public function getSelectedStepResultPreviewProperty(): string
    {
        if ($this->selectedStep === null) {
            return Str::markdown('Шаги прогона пока не добавлены.');
        }

        if ($this->selectedStepResult === '') {
            return Str::markdown('Результат для выбранного шага пока не сформирован.');
        }

        return Str::markdown($this->selectedStepResult, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function getSelectedStepErrorMessageProperty(): ?string
    {
        if ($this->selectedStep === null || $this->selectedStep->status !== 'failed') {
            return null;
        }

        if (! blank($this->selectedStep->result)) {
            return null;
        }

        $error = trim((string) ($this->selectedStep->error ?? ''));

        return $error !== '' ? $error : 'Шаг завершился с ошибкой.';
    }

    public function getPipelineVersionLabelProperty(): string
    {
        $pipelineTitle = $this->pipelineRun->pipelineVersion?->title ?? 'Без названия';

        if ($this->isZeroAccessLevelUser) {
            return $pipelineTitle;
        }

        return sprintf(
            '%s • v%s',
            $pipelineTitle,
            (string) ($this->pipelineRun->pipelineVersion?->version ?? '—')
        );
    }

    public function stepStatusLabel(?string $status): string
    {
        return match ($status) {
            'done' => 'Готово',
            'pending' => 'В очереди',
            'running' => 'Обработка',
            'paused' => 'На паузе',
            'failed' => 'Ошибка',
            default => 'Неизвестно',
        };
    }

    public function stepStatusBadgeClass(?string $status): string
    {
        return match ($status) {
            'done' => 'inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700 dark:bg-green-400/10 dark:text-green-400',
            'pending' => 'inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-400/10 dark:text-gray-400',
            'running' => 'inline-flex items-center rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800 dark:bg-amber-400/10 dark:text-amber-300',
            'paused' => 'inline-flex items-center rounded-full bg-sky-100 px-2 py-1 text-xs font-medium text-sky-700 dark:bg-sky-400/10 dark:text-sky-300',
            'failed' => 'inline-flex items-center rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700 dark:bg-red-400/10 dark:text-red-400',
            default => 'inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-400/10 dark:text-gray-400',
        };
    }

    public function getHasPausedStepsProperty(): bool
    {
        return $this->pipelineRun->steps->contains(
            fn (PipelineRunStep $step): bool => $step->status === 'paused'
        );
    }

    public function getHasQueuedStepsProperty(): bool
    {
        return $this->pipelineRun->steps->contains(
            fn (PipelineRunStep $step): bool => $step->status === 'pending'
        );
    }

    public function getHasFailedStepsProperty(): bool
    {
        return $this->pipelineRun->steps->contains(
            fn (PipelineRunStep $step): bool => $step->status === 'failed'
        );
    }

    public function getHasRunningStepsProperty(): bool
    {
        return $this->pipelineRun->steps->contains(
            fn (PipelineRunStep $step): bool => $step->status === 'running'
        );
    }

    public function getHasUnfinishedStepsProperty(): bool
    {
        return $this->pipelineRun->steps->contains(
            fn (PipelineRunStep $step): bool => $step->status !== 'done'
        );
    }

    public function getShouldPollSelectedStepResultProperty(): bool
    {
        return $this->selectedStep?->status === 'running';
    }

    public function tokenMetricsBadgeClass(): string
    {
        return 'inline-flex items-center rounded-full bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300';
    }

    public function costMetricsBadgeClass(): string
    {
        return 'inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-400/10 dark:text-amber-300';
    }

    public function formatTokens(?int $tokens): string
    {
        return number_format($tokens ?? 0, 0, '.', ',');
    }

    public function formatCost(float|int|string|null $cost): string
    {
        return number_format((float) ($cost ?? 0), 3, '.', ',');
    }

    public function getCanExportSelectedStepProperty(): bool
    {
        return $this->selectedStep !== null && ! blank($this->selectedStep->result);
    }

    public function getCanRestartSelectedStepProperty(): bool
    {
        return $this->selectedStep !== null;
    }

    private function selectedStepForExport(): PipelineRunStep
    {
        abort_if($this->selectedStep === null, 422, 'Шаг для экспорта не выбран.');
        abort_if(blank($this->selectedStep->result), 422, 'У шага нет результата для экспорта.');
        abort_if($this->selectedStep->pipeline_run_id !== $this->pipelineRun->id, 404, 'Шаг не принадлежит указанному прогону.');

        return $this->selectedStep;
    }

    private function selectedStepExportFilename(PipelineRunStep $step, string $extension): string
    {
        $lessonName = $this->pipelineRun->lesson?->name ?? 'lesson';
        $stepName = $step->stepVersion?->name ?? 'step';

        return Str::slug($lessonName.'-'.$stepName, '_').'.'.$extension;
    }

    private function resolveInitialSelectedStepId(): ?int
    {
        $defaultStep = $this->resolveDefaultRunStep();
        $lastDoneStep = $this->pipelineRun->steps->last(
            fn (PipelineRunStep $step): bool => $step->status === 'done'
        );

        if ($defaultStep !== null && $defaultStep->status === 'done') {
            return $defaultStep->id;
        }

        if ($lastDoneStep !== null) {
            return $lastDoneStep->id;
        }

        if ($defaultStep !== null) {
            return $defaultStep->id;
        }

        return $this->pipelineRun->steps->first()?->id;
    }

    private function resolveDefaultRunStep(): ?PipelineRunStep
    {
        $defaultStep = $this->pipelineRun->steps
            ->first(fn (PipelineRunStep $step): bool => (bool) data_get($step->stepVersion?->settings, 'is_default', false));

        if ($defaultStep !== null) {
            return $defaultStep;
        }

        return $this->pipelineRun->steps->last();
    }

    public function render(): View
    {
        $lessonName = $this->pipelineRun->lesson?->name ?? 'Урок';

        return view('pages.project-run-page', [
            'project' => $this->project,
            'pipelineRun' => $this->pipelineRun,
        ])->layout('layouts.app', [
            'title' => $lessonName.' | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [
                ['label' => 'Проекты', 'url' => route('projects.index')],
                ['label' => $this->project->name, 'url' => route('projects.show', $this->project)],
                ['label' => $lessonName],
                ['label' => $this->pipelineVersionLabel, 'current' => true],
            ],
        ]);
    }
}
