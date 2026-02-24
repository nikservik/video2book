<?php

namespace App\Livewire;

use App\Actions\Pipeline\GetPipelineVersionOptionsAction;
use App\Actions\Project\CreateFolderAction;
use App\Actions\Project\CreateProjectFromLessonsListAction;
use App\Actions\Project\MoveProjectToFolderAction;
use App\Actions\Project\RecalculateProjectLessonsAudioDurationAction;
use App\Actions\Project\RenameFolderAction;
use App\Models\Folder;
use App\Models\Project;
use App\Services\Project\ProjectFoldersQuery;
use App\Support\AudioDurationLabelFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ProjectsPage extends Component
{
    public ?int $expandedFolderId = null;

    public bool $showCreateFolderModal = false;

    public string $newFolderName = '';

    public bool $showRenameFolderModal = false;

    public ?int $editingFolderId = null;

    public string $editingFolderName = '';

    public bool $showCreateProjectModal = false;

    public ?int $newProjectFolderId = null;

    public string $newProjectName = '';

    public string $newProjectReferer = '';

    public ?int $newProjectDefaultPipelineVersionId = null;

    public string $newProjectLessonsList = '';

    /**
     * @var array<int, array{id:int,label:string,description:string|null}>
     */
    public array $pipelineVersionOptions = [];

    public function mount(): void
    {
        $this->pipelineVersionOptions = app(GetPipelineVersionOptionsAction::class)->handle();
        $this->expandedFolderId = Folder::query()->orderBy('name')->value('id');
    }

    public function openCreateFolderModal(): void
    {
        $this->resetErrorBag();
        $this->newFolderName = '';
        $this->showCreateFolderModal = true;
    }

    public function closeCreateFolderModal(): void
    {
        $this->showCreateFolderModal = false;
    }

    public function createFolder(CreateFolderAction $action): void
    {
        $validated = validator([
            'newFolderName' => $this->newFolderName,
        ], [
            'newFolderName' => ['required', 'string', 'max:255', 'unique:folders,name'],
        ], [], [
            'newFolderName' => 'название папки',
        ])->validate();

        $folder = $action->handle($validated['newFolderName']);

        $this->expandedFolderId = (int) $folder->id;
        $this->closeCreateFolderModal();
    }

    public function openRenameFolderModal(int $folderId): void
    {
        $folder = Folder::query()->findOrFail($folderId);

        $this->resetErrorBag();
        $this->editingFolderId = $folder->id;
        $this->editingFolderName = $folder->name;
        $this->showRenameFolderModal = true;
    }

    public function closeRenameFolderModal(): void
    {
        $this->showRenameFolderModal = false;
        $this->editingFolderId = null;
        $this->editingFolderName = '';
    }

    public function renameFolder(RenameFolderAction $action): void
    {
        $folderId = $this->editingFolderId;

        if ($folderId === null) {
            return;
        }

        $validated = validator([
            'editingFolderName' => $this->editingFolderName,
        ], [
            'editingFolderName' => ['required', 'string', 'max:255', Rule::unique('folders', 'name')->ignore($folderId)],
        ], [], [
            'editingFolderName' => 'название папки',
        ])->validate();

        $action->handle(
            folder: Folder::query()->findOrFail($folderId),
            name: $validated['editingFolderName'],
        );

        $this->closeRenameFolderModal();
    }

    public function toggleFolder(int $folderId): void
    {
        $this->expandedFolderId = $this->expandedFolderId === $folderId ? null : $folderId;
    }

    public function openCreateProjectModal(?int $folderId = null): void
    {
        $this->resetErrorBag();
        $this->newProjectFolderId = $folderId ?? $this->expandedFolderId ?? Folder::query()->orderBy('name')->value('id');
        $this->newProjectName = '';
        $this->newProjectReferer = '';
        $this->newProjectDefaultPipelineVersionId = null;
        $this->newProjectLessonsList = '';
        $this->showCreateProjectModal = true;
    }

    public function closeCreateProjectModal(): void
    {
        $this->showCreateProjectModal = false;
    }

    public function createProject(CreateProjectFromLessonsListAction $action): void
    {
        $availablePipelineVersionIds = $this->availablePipelineVersionIds();

        $normalizedData = [
            'newProjectFolderId' => $this->newProjectFolderId,
            'newProjectName' => $this->newProjectName,
            'newProjectReferer' => blank($this->newProjectReferer) ? null : trim($this->newProjectReferer),
            'newProjectDefaultPipelineVersionId' => $this->newProjectDefaultPipelineVersionId,
            'newProjectLessonsList' => blank($this->newProjectLessonsList) ? null : $this->newProjectLessonsList,
        ];

        $validated = validator($normalizedData, [
            'newProjectFolderId' => ['required', 'integer', Rule::exists('folders', 'id')],
            'newProjectName' => ['required', 'string', 'max:255'],
            'newProjectReferer' => ['nullable', 'url', 'starts_with:https://'],
            'newProjectDefaultPipelineVersionId' => ['nullable', 'integer', Rule::in($availablePipelineVersionIds)],
            'newProjectLessonsList' => ['nullable', 'string'],
        ], [], [
            'newProjectFolderId' => 'папка',
            'newProjectName' => 'название проекта',
            'newProjectReferer' => 'referer',
            'newProjectDefaultPipelineVersionId' => 'версия шаблона по умолчанию',
            'newProjectLessonsList' => 'список уроков',
        ])->validate();

        $action->handle(
            folderId: (int) $validated['newProjectFolderId'],
            projectName: $validated['newProjectName'],
            referer: $validated['newProjectReferer'],
            defaultPipelineVersionId: $validated['newProjectDefaultPipelineVersionId'] === null
                ? null
                : (int) $validated['newProjectDefaultPipelineVersionId'],
            lessonsList: $validated['newProjectLessonsList'],
        );

        $this->expandedFolderId = (int) $validated['newProjectFolderId'];
        $this->closeCreateProjectModal();
    }

    public function moveProjectToFolder(
        int $projectId,
        int $targetFolderId,
        MoveProjectToFolderAction $action
    ): void {
        if ($this->expandedFolderId === null || $targetFolderId === $this->expandedFolderId) {
            return;
        }

        $validated = validator([
            'projectId' => $projectId,
            'targetFolderId' => $targetFolderId,
        ], [
            'projectId' => ['required', 'integer', Rule::exists('projects', 'id')],
            'targetFolderId' => ['required', 'integer', Rule::exists('folders', 'id')],
        ])->validate();

        $project = Project::query()->findOrFail((int) $validated['projectId']);

        if ((int) $project->folder_id !== $this->expandedFolderId) {
            return;
        }

        $action->handle(
            project: $project,
            folder: Folder::query()->findOrFail((int) $validated['targetFolderId']),
        );

        $this->expandedFolderId = (int) $validated['targetFolderId'];
    }

    public function updatedNewProjectDefaultPipelineVersionId($value): void
    {
        $this->newProjectDefaultPipelineVersionId = $value === '' || $value === null ? null : (int) $value;
    }

    public function getSelectedDefaultPipelineVersionLabelProperty(): string
    {
        return data_get(
            collect($this->pipelineVersionOptions)->firstWhere('id', $this->newProjectDefaultPipelineVersionId),
            'label',
            'Не выбрано'
        );
    }

    public function getSelectedProjectFolderNameProperty(): string
    {
        if ($this->newProjectFolderId === null) {
            return 'папку';
        }

        $folderName = Folder::query()->whereKey($this->newProjectFolderId)->value('name');

        return is_string($folderName) && $folderName !== '' ? $folderName : 'папку';
    }

    public function projectDurationLabel(?array $settings): string
    {
        return app(AudioDurationLabelFormatter::class)->format(
            data_get($settings, RecalculateProjectLessonsAudioDurationAction::PROJECT_TOTAL_DURATION_SETTING_KEY)
        ) ?? '—';
    }

    /**
     * @return array<int, int>
     */
    private function availablePipelineVersionIds(): array
    {
        return collect($this->pipelineVersionOptions)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function render(): View
    {
        return view('pages.projects-page', [
            'folders' => app(ProjectFoldersQuery::class)->get(),
            'pipelineVersionOptions' => $this->pipelineVersionOptions,
        ])->layout('layouts.app', [
            'title' => 'Проекты | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [
                ['label' => 'Проекты', 'current' => true],
            ],
        ]);
    }
}
