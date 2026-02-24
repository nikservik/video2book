<?php

namespace App\Livewire;

use App\Actions\Project\RecalculateProjectLessonsAudioDurationAction;
use App\Models\Lesson;
use App\Models\PipelineRun;
use App\Models\Project;
use App\Models\User;
use App\Services\Project\ProjectDetailsQuery;
use App\Support\AudioDurationLabelFormatter;
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

    public function recalculateProjectAudioDuration(
        RecalculateProjectLessonsAudioDurationAction $recalculateProjectLessonsAudioDurationAction
    ): void {
        $project = $this->project->fresh();
        $recalculateProjectLessonsAudioDurationAction->handle($project);

        $this->reloadProjectDetails();
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
            'done' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                          <path fill-rule="evenodd" d="M12.416 3.376a.75.75 0 0 1 .208 1.04l-5 7.5a.75.75 0 0 1-1.154.114l-3-3a.75.75 0 0 1 1.06-1.06l2.353 2.353 4.493-6.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd" />
                        </svg>',
            'queued' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                          <path fill-rule="evenodd" d="M1 8a7 7 0 1 1 14 0A7 7 0 0 1 1 8Zm7.75-4.25a.75.75 0 0 0-1.5 0V8c0 .414.336.75.75.75h3.25a.75.75 0 0 0 0-1.5h-2.5v-3.5Z" clip-rule="evenodd" />
                        </svg>',
            'running' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                          <path fill-rule="evenodd" d="M12.78 7.595a.75.75 0 0 1 0 1.06l-3.25 3.25a.75.75 0 0 1-1.06-1.06l2.72-2.72-2.72-2.72a.75.75 0 0 1 1.06-1.06l3.25 3.25Zm-8.25-3.25 3.25 3.25a.75.75 0 0 1 0 1.06l-3.25 3.25a.75.75 0 0 1-1.06-1.06l2.72-2.72-2.72-2.72a.75.75 0 0 1 1.06-1.06Z" clip-rule="evenodd" />
                        </svg>',
            'paused' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                          <path d="M4.5 2a.5.5 0 0 0-.5.5v11a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-11a.5.5 0 0 0-.5-.5h-1ZM10.5 2a.5.5 0 0 0-.5.5v11a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-11a.5.5 0 0 0-.5-.5h-1Z" />
                        </svg>',
            'failed' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                          <path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14ZM8 4a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-1.5 0v-3A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                        </svg>',
            default => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                          <path fill-rule="evenodd" d="M15 8A7 7 0 1 1 1 8a7 7 0 0 1 14 0Zm-6 3.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM7.293 5.293a1 1 0 1 1 .99 1.667c-.459.134-1.033.566-1.033 1.29v.25a.75.75 0 1 0 1.5 0v-.115a2.5 2.5 0 1 0-2.518-4.153.75.75 0 1 0 1.061 1.06Z" clip-rule="evenodd" />
                        </svg>',
        };
    }

    public function pipelineRunStatusBadgeClass(?string $status): string
    {
        return match ($status) {
            'done' => 'inline-flex items-center rounded-full bg-green-100 -mr-1 p-0.5 text-xs font-medium whitespace-nowrap text-green-700 dark:bg-green-400/10 dark:text-green-400',
            'queued' => 'inline-flex items-center rounded-full bg-gray-100 -mr-1 p-0.5 text-xs font-medium whitespace-nowrap text-gray-600 dark:bg-gray-400/10 dark:text-gray-400',
            'running' => 'inline-flex items-center rounded-full bg-amber-100 -mr-1 p-0.5 text-xs font-medium whitespace-nowrap text-amber-800 dark:bg-amber-400/10 dark:text-amber-300',
            'paused' => 'inline-flex items-center rounded-full bg-sky-100 -mr-1 p-0.5 text-xs font-medium whitespace-nowrap text-sky-700 dark:bg-sky-400/10 dark:text-sky-300',
            'failed' => 'inline-flex items-center rounded-full bg-red-100 -mr-1 p-0.5 text-xs font-medium whitespace-nowrap text-red-700 dark:bg-red-400/10 dark:text-red-400',
            default => 'inline-flex items-center rounded-full bg-gray-100 -mr-1 p-0.5 text-xs font-medium whitespace-nowrap text-gray-600 dark:bg-gray-400/10 dark:text-gray-400',
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

        return app(AudioDurationLabelFormatter::class)
            ->format(data_get($settings, 'audio_duration_seconds'));
    }

    public function projectLessonsAudioDurationLabel(?array $settings): ?string
    {
        return app(AudioDurationLabelFormatter::class)
            ->format(data_get($settings, RecalculateProjectLessonsAudioDurationAction::PROJECT_TOTAL_DURATION_SETTING_KEY));
    }

    public function lessonHasSinglePipelineRun(Lesson $lesson): bool
    {
        return $lesson->pipelineRuns->count() === 1;
    }

    public function lessonSinglePipelineRunUrl(Lesson $lesson): ?string
    {
        if (! $this->lessonHasSinglePipelineRun($lesson)) {
            return null;
        }

        $pipelineRun = $lesson->pipelineRuns->first();

        if (! $pipelineRun instanceof PipelineRun) {
            return null;
        }

        return route('projects.runs.show', [
            'project' => $this->project,
            'pipelineRun' => $pipelineRun,
        ]);
    }

    public function render(): View
    {
        return view('pages.project-show-page', [
            'project' => $this->project,
        ])->layout('layouts.app', [
            'title' => $this->project->name.' | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [
                ['label' => 'Проекты', 'url' => route('projects.index', ['f' => $this->project->folder_id])],
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
