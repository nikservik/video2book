<?php

namespace App\Livewire;

use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\Project;
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

    public string $resultViewMode = 'preview';

    public function mount(Project $project, PipelineRun $pipelineRun): void
    {
        $this->pipelineRun = app(ProjectRunDetailsQuery::class)->get($pipelineRun);

        abort_unless(
            $this->pipelineRun->lesson?->project_id === $project->id,
            404
        );

        $this->project = $project;
        $lastDoneStep = $this->pipelineRun->steps->last(
            fn (PipelineRunStep $step): bool => $step->status === 'done'
        );

        $this->selectedStepId = $lastDoneStep?->id ?? $this->pipelineRun->steps->first()?->id;
    }

    public function selectStep(int $pipelineRunStepId): void
    {
        abort_unless($this->pipelineRun->steps->contains('id', $pipelineRunStepId), 404);

        $this->selectedStepId = $pipelineRunStepId;
    }

    public function setResultViewMode(string $mode): void
    {
        if (! in_array($mode, ['preview', 'source'], true)) {
            return;
        }

        $this->resultViewMode = $mode;
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

    public function getSelectedStepProperty(): ?PipelineRunStep
    {
        return $this->pipelineRun->steps->firstWhere('id', $this->selectedStepId);
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

    public function getPipelineVersionLabelProperty(): string
    {
        return sprintf(
            '%s • v%s',
            $this->pipelineRun->pipelineVersion?->title ?? 'Без названия',
            (string) ($this->pipelineRun->pipelineVersion?->version ?? '—')
        );
    }

    public function stepStatusLabel(?string $status): string
    {
        return match ($status) {
            'done' => 'Готово',
            'pending' => 'В очереди',
            'running' => 'Обработка',
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
            'failed' => 'inline-flex items-center rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700 dark:bg-red-400/10 dark:text-red-400',
            default => 'inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-400/10 dark:text-gray-400',
        };
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
        return $this->selectedStep !== null && $this->selectedStep->result !== null;
    }

    private function selectedStepForExport(): PipelineRunStep
    {
        abort_if($this->selectedStep === null, 422, 'Шаг для экспорта не выбран.');
        abort_if($this->selectedStep->result === null, 422, 'У шага нет результата для экспорта.');
        abort_if($this->selectedStep->pipeline_run_id !== $this->pipelineRun->id, 404, 'Шаг не принадлежит указанному прогону.');

        return $this->selectedStep;
    }

    private function selectedStepExportFilename(PipelineRunStep $step, string $extension): string
    {
        $lessonName = $this->pipelineRun->lesson?->name ?? 'lesson';
        $stepName = $step->stepVersion?->name ?? 'step';

        return Str::slug($lessonName.'-'.$stepName, '_').'.'.$extension;
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
