<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\User;
use App\Services\Project\ProjectDetailsQuery;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class ProjectShowPage extends Component
{
    private const LESSON_SORT_SETTING_KEY = 'lessons_sort';

    public Project $project;

    public int $openedModalCount = 0;

    public bool $isAudioUploadInProgress = false;

    public bool $isLessonSortDropdownOpen = false;

    public bool $showPipelineRunVersionInLessonCard = true;

    public string $lessonSort = ProjectDetailsQuery::LESSON_SORT_CREATED_AT;

    public function mount(Project $project): void
    {
        $authUser = auth()->user();
        $this->showPipelineRunVersionInLessonCard = ! ($authUser instanceof User
            && (int) $authUser->access_level === User::ACCESS_LEVEL_USER);

        $this->lessonSort = $this->resolveLessonSortSetting(
            data_get($project->settings, self::LESSON_SORT_SETTING_KEY)
        );
        $this->project = $this->loadProjectDetails($project);
    }

    #[On('project-show:project-updated')]
    public function refreshProjectLessons(): void
    {
        $this->reloadProjectDetails();
    }

    #[On('project-show:modal-opened')]
    public function markModalOpened(): void
    {
        $this->openedModalCount++;
        $this->reloadProjectDetails();
    }

    #[On('project-show:modal-closed')]
    public function markModalClosed(): void
    {
        $this->openedModalCount = max(0, $this->openedModalCount - 1);
        $this->reloadProjectDetails();
    }

    #[On('project-show:audio-upload-started')]
    public function markAudioUploadStarted(): void
    {
        $this->isAudioUploadInProgress = true;
    }

    #[On('project-show:audio-upload-finished')]
    public function markAudioUploadFinished(): void
    {
        $this->isAudioUploadInProgress = false;
    }

    public function markLessonSortDropdownOpened(): void
    {
        $this->isLessonSortDropdownOpen = true;
        $this->reloadProjectDetails();
    }

    public function markLessonSortDropdownClosed(): void
    {
        $this->isLessonSortDropdownOpen = false;
        $this->reloadProjectDetails();
    }

    public function updatedLessonSort($value): void
    {
        $normalizedSort = $this->resolveLessonSortSetting($value);
        $this->lessonSort = $normalizedSort;

        $project = $this->project->fresh();
        $settings = $project->settings ?? [];
        $settings[self::LESSON_SORT_SETTING_KEY] = $normalizedSort;

        $project->forceFill(['settings' => $settings])->save();
        $this->project = $this->loadProjectDetails($project->fresh());
    }

    public function getShouldPollProjectLessonsProperty(): bool
    {
        return $this->openedModalCount === 0
            && ! $this->isAudioUploadInProgress
            && ! $this->isLessonSortDropdownOpen;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function lessonSortOptions(): array
    {
        return [
            [
                'value' => ProjectDetailsQuery::LESSON_SORT_CREATED_AT,
                'label' => 'Сортировка по дате добавления',
            ],
            [
                'value' => ProjectDetailsQuery::LESSON_SORT_NAME,
                'label' => 'Сортировка по названию',
            ],
        ];
    }

    public function getSelectedLessonSortLabelProperty(): string
    {
        return collect($this->lessonSortOptions())
            ->firstWhere('value', $this->lessonSort)['label'] ?? 'Сортировка по дате добавления';
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
            'done' => 'inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-medium whitespace-nowrap text-green-700 dark:bg-green-400/10 dark:text-green-400',
            'queued' => 'inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium whitespace-nowrap text-gray-600 dark:bg-gray-400/10 dark:text-gray-400',
            'running' => 'inline-flex items-center rounded-full bg-amber-100 px-2 py-1 text-xs font-medium whitespace-nowrap text-amber-800 dark:bg-amber-400/10 dark:text-amber-300',
            'paused' => 'inline-flex items-center rounded-full bg-sky-100 px-2 py-1 text-xs font-medium whitespace-nowrap text-sky-700 dark:bg-sky-400/10 dark:text-sky-300',
            'failed' => 'inline-flex items-center rounded-full bg-red-100 px-2 py-1 text-xs font-medium whitespace-nowrap text-red-700 dark:bg-red-400/10 dark:text-red-400',
            default => 'inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium whitespace-nowrap text-gray-600 dark:bg-gray-400/10 dark:text-gray-400',
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

    public function lessonAudioDownloadErrorTooltip(?array $settings, ?string $sourceFilename): ?string
    {
        if ($this->lessonAudioDownloadStatus($settings, $sourceFilename) !== 'failed') {
            return null;
        }

        $error = trim((string) data_get($settings, 'download_error', ''));

        return $error !== '' ? $error : 'Ошибка загрузки аудио';
    }

    public function lessonAudioDurationLabel(?array $settings, ?string $sourceFilename): ?string
    {
        if ($this->lessonAudioDownloadStatus($settings, $sourceFilename) !== 'loaded') {
            return null;
        }

        $durationSeconds = data_get($settings, 'audio_duration_seconds');

        if (! is_numeric($durationSeconds)) {
            return null;
        }

        $durationSeconds = (int) $durationSeconds;

        if ($durationSeconds <= 0) {
            return null;
        }

        $hours = intdiv($durationSeconds, 3600);
        $minutes = intdiv($durationSeconds % 3600, 60);

        return sprintf('%02d:%02d', $hours, $minutes);
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
        return app(ProjectDetailsQuery::class)->get($project, $this->lessonSort);
    }

    private function reloadProjectDetails(): void
    {
        $project = $this->project->fresh();
        $this->lessonSort = $this->resolveLessonSortSetting(
            data_get($project->settings, self::LESSON_SORT_SETTING_KEY)
        );
        $this->project = $this->loadProjectDetails($project);
    }

    private function resolveLessonSortSetting(mixed $value): string
    {
        return match ((string) $value) {
            ProjectDetailsQuery::LESSON_SORT_NAME => ProjectDetailsQuery::LESSON_SORT_NAME,
            default => ProjectDetailsQuery::LESSON_SORT_CREATED_AT,
        };
    }
}
