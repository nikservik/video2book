<?php

namespace App\Livewire;

use App\Models\Project;
use App\Services\Project\ProjectDetailsQuery;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class ProjectShowPage extends Component
{
    public Project $project;

    public int $openedModalCount = 0;

    public function mount(Project $project): void
    {
        $this->project = $this->loadProjectDetails($project);
    }

    #[On('project-show:project-updated')]
    public function refreshProjectLessons(): void
    {
        $this->project = $this->loadProjectDetails($this->project->fresh());
    }

    #[On('project-show:modal-opened')]
    public function markModalOpened(): void
    {
        $this->openedModalCount++;
        $this->project = $this->loadProjectDetails($this->project->fresh());
    }

    #[On('project-show:modal-closed')]
    public function markModalClosed(): void
    {
        $this->openedModalCount = max(0, $this->openedModalCount - 1);
        $this->project = $this->loadProjectDetails($this->project->fresh());
    }

    public function getShouldPollProjectLessonsProperty(): bool
    {
        return $this->openedModalCount === 0;
    }

    public function pipelineRunStatusLabel(?string $status): string
    {
        return match ($status) {
            'done' => 'Готово',
            'queued' => 'В очереди',
            'running' => 'Обработка',
            'paused' => 'На паузе',
            'failed' => 'Ошибка',
            default => 'Неизвестно',
        };
    }

    public function pipelineRunStatusBadgeClass(?string $status): string
    {
        return match ($status) {
            'done' => 'inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700 dark:bg-green-400/10 dark:text-green-400',
            'queued' => 'inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-400/10 dark:text-gray-400',
            'running' => 'inline-flex items-center rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800 dark:bg-amber-400/10 dark:text-amber-300',
            'paused' => 'inline-flex items-center rounded-full bg-sky-100 px-2 py-1 text-xs font-medium text-sky-700 dark:bg-sky-400/10 dark:text-sky-300',
            'failed' => 'inline-flex items-center rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700 dark:bg-red-400/10 dark:text-red-400',
            default => 'inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-400/10 dark:text-gray-400',
        };
    }

    public function lessonAudioDownloadStatus(?array $settings, ?string $sourceFilename): string
    {
        if (! blank($sourceFilename)) {
            return 'loaded';
        }

        $downloadStatus = data_get($settings, 'download_status');
        $downloadError = data_get($settings, 'download_error');
        $downloading = (bool) data_get($settings, 'downloading', false);

        if ($downloadStatus === 'failed' || ! blank($downloadError)) {
            return 'failed';
        }

        if ($downloadStatus === 'completed') {
            return 'loaded';
        }

        if ($downloadStatus === 'queued') {
            return 'queued';
        }

        if ($downloadStatus === 'running' || $downloading) {
            return 'running';
        }

        return 'queued';
    }

    public function lessonAudioDownloadIconClass(?array $settings, ?string $sourceFilename): string
    {
        return match ($this->lessonAudioDownloadStatus($settings, $sourceFilename)) {
            'failed' => 'text-red-500 dark:text-red-400',
            'running' => 'text-yellow-500 dark:text-yellow-400',
            'loaded' => 'text-green-500 dark:text-green-400',
            default => 'text-gray-500 dark:text-gray-400',
        };
    }

    public function render(): View
    {
        return view('pages.project-show-page', [
            'project' => $this->project,
        ])->layout('layouts.app', [
            'title' => $this->project->name.' | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [
                ['label' => 'Проекты', 'url' => route('projects.index')],
                ['label' => $this->project->name, 'current' => true],
            ],
        ]);
    }

    private function loadProjectDetails(Project $project): Project
    {
        return app(ProjectDetailsQuery::class)->get($project);
    }
}
