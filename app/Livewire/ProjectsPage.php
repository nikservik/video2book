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
use App\Models\User;
use App\Services\Project\ProjectFoldersQuery;
use App\Support\AudioDurationLabelFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ProjectsPage extends Component
{
    public ?int $expandedFolderId = null;

    public bool $showCreateFolderModal = false;

    public string $newFolderName = '';

    public bool $newFolderHidden = false;

    /**
     * @var array<int, int|string>
     */
    public array $newFolderVisibleFor = [];

    public bool $showRenameFolderModal = false;

    public ?int $editingFolderId = null;

    public string $editingFolderName = '';

    public bool $editingFolderHidden = false;

    /**
     * @var array<int, int|string>
     */
    public array $editingFolderVisibleFor = [];

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

    /**
     * @var array<int, array{id:int,name:string,access_level:int}>
     */
    public array $folderVisibilityUsers = [];

    /**
     * @var array<int, int>
     */
    public array $lockedFolderVisibilityUserIds = [];

    public function mount(): void
    {
        $this->pipelineVersionOptions = app(GetPipelineVersionOptionsAction::class)->handle();
        $this->hydrateFolderVisibilityOptions();
        $this->expandedFolderId = $this->defaultExpandedFolderId();
    }

    public function openCreateFolderModal(): void
    {
        $this->resetErrorBag();
        $this->newFolderName = '';
        $this->newFolderHidden = false;
        $this->newFolderVisibleFor = [];
        $this->showCreateFolderModal = true;
    }

    public function closeCreateFolderModal(): void
    {
        $this->showCreateFolderModal = false;
        $this->newFolderName = '';
        $this->newFolderHidden = false;
        $this->newFolderVisibleFor = [];
    }

    public function createFolder(CreateFolderAction $action): void
    {
        $normalizedData = [
            'newFolderName' => trim($this->newFolderName),
            'newFolderHidden' => (bool) $this->newFolderHidden,
            'newFolderVisibleFor' => $this->newFolderHidden
                ? $this->normalizeVisibleFor($this->newFolderVisibleFor)
                : [],
        ];

        $validated = validator($normalizedData, [
            'newFolderName' => ['required', 'string', 'max:255', 'unique:folders,name'],
            'newFolderHidden' => ['required', 'boolean'],
            'newFolderVisibleFor' => ['array'],
            'newFolderVisibleFor.*' => ['integer', Rule::exists('users', 'id')],
        ], [], [
            'newFolderName' => 'название папки',
            'newFolderHidden' => 'скрытие папки',
            'newFolderVisibleFor' => 'список пользователей',
        ])->validate();

        $folder = $action->handle(
            name: $validated['newFolderName'],
            hidden: (bool) $validated['newFolderHidden'],
            visibleFor: $this->enforceLockedVisibility($validated['newFolderVisibleFor']),
        );

        $this->expandedFolderId = (int) $folder->id;
        $this->closeCreateFolderModal();
    }

    public function openRenameFolderModal(int $folderId): void
    {
        $folder = $this->visibleFoldersQuery()->findOrFail($folderId);

        $this->resetErrorBag();
        $this->editingFolderId = (int) $folder->id;
        $this->editingFolderName = (string) $folder->name;
        $this->editingFolderHidden = (bool) $folder->hidden;
        $this->editingFolderVisibleFor = $this->editingFolderHidden
            ? $this->enforceLockedVisibility((array) ($folder->visible_for ?? []))
            : [];
        $this->showRenameFolderModal = true;
    }

    public function closeRenameFolderModal(): void
    {
        $this->showRenameFolderModal = false;
        $this->editingFolderId = null;
        $this->editingFolderName = '';
        $this->editingFolderHidden = false;
        $this->editingFolderVisibleFor = [];
    }

    public function renameFolder(RenameFolderAction $action): void
    {
        $folderId = $this->editingFolderId;

        if ($folderId === null) {
            return;
        }

        $folder = $this->visibleFoldersQuery()->find($folderId);

        if (! $folder instanceof Folder) {
            $this->closeRenameFolderModal();

            return;
        }

        $normalizedData = [
            'editingFolderName' => trim($this->editingFolderName),
            'editingFolderHidden' => (bool) $this->editingFolderHidden,
            'editingFolderVisibleFor' => $this->editingFolderHidden
                ? $this->normalizeVisibleFor($this->editingFolderVisibleFor)
                : [],
        ];

        $validated = validator($normalizedData, [
            'editingFolderName' => ['required', 'string', 'max:255', Rule::unique('folders', 'name')->ignore($folderId)],
            'editingFolderHidden' => ['required', 'boolean'],
            'editingFolderVisibleFor' => ['array'],
            'editingFolderVisibleFor.*' => ['integer', Rule::exists('users', 'id')],
        ], [], [
            'editingFolderName' => 'название папки',
            'editingFolderHidden' => 'скрытие папки',
            'editingFolderVisibleFor' => 'список пользователей',
        ])->validate();

        $action->handle(
            folder: $folder,
            name: $validated['editingFolderName'],
            hidden: (bool) $validated['editingFolderHidden'],
            visibleFor: $this->enforceLockedVisibility($validated['editingFolderVisibleFor']),
        );

        $this->closeRenameFolderModal();
    }

    public function toggleFolder(int $folderId): void
    {
        if (! in_array($folderId, $this->visibleFolderIds(), true)) {
            return;
        }

        $this->expandedFolderId = $this->expandedFolderId === $folderId ? null : $folderId;
    }

    public function openCreateProjectModal(?int $folderId = null): void
    {
        $visibleFolderIds = $this->visibleFolderIds();
        $selectedFolderId = $folderId;

        if ($selectedFolderId !== null && ! in_array($selectedFolderId, $visibleFolderIds, true)) {
            $selectedFolderId = null;
        }

        if ($selectedFolderId === null && $this->expandedFolderId !== null && in_array($this->expandedFolderId, $visibleFolderIds, true)) {
            $selectedFolderId = $this->expandedFolderId;
        }

        if ($selectedFolderId === null) {
            $selectedFolderId = $visibleFolderIds[0] ?? null;
        }

        $this->resetErrorBag();
        $this->newProjectFolderId = $selectedFolderId;
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
        $visibleFolderIds = $this->visibleFolderIds();

        $normalizedData = [
            'newProjectFolderId' => $this->newProjectFolderId,
            'newProjectName' => $this->newProjectName,
            'newProjectReferer' => blank($this->newProjectReferer) ? null : trim($this->newProjectReferer),
            'newProjectDefaultPipelineVersionId' => $this->newProjectDefaultPipelineVersionId,
            'newProjectLessonsList' => blank($this->newProjectLessonsList) ? null : $this->newProjectLessonsList,
        ];

        $validated = validator($normalizedData, [
            'newProjectFolderId' => ['required', 'integer', Rule::in($visibleFolderIds)],
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
        $visibleFolderIds = $this->visibleFolderIds();

        if ($this->expandedFolderId === null || $targetFolderId === $this->expandedFolderId) {
            return;
        }

        $validated = validator([
            'projectId' => $projectId,
            'targetFolderId' => $targetFolderId,
        ], [
            'projectId' => ['required', 'integer', Rule::exists('projects', 'id')],
            'targetFolderId' => ['required', 'integer', Rule::in($visibleFolderIds)],
        ])->validate();

        $project = Project::query()->findOrFail((int) $validated['projectId']);

        if ((int) $project->folder_id !== $this->expandedFolderId) {
            return;
        }

        $action->handle(
            project: $project,
            folder: $this->visibleFoldersQuery()->findOrFail((int) $validated['targetFolderId']),
        );

        $this->expandedFolderId = (int) $validated['targetFolderId'];
    }

    public function updatedNewFolderHidden(bool $isHidden): void
    {
        $this->newFolderVisibleFor = $isHidden
            ? $this->enforceLockedVisibility($this->newFolderVisibleFor)
            : [];
    }

    public function updatedNewFolderVisibleFor(): void
    {
        if (! $this->newFolderHidden) {
            $this->newFolderVisibleFor = [];

            return;
        }

        $this->newFolderVisibleFor = $this->enforceLockedVisibility($this->newFolderVisibleFor);
    }

    public function updatedEditingFolderHidden(bool $isHidden): void
    {
        $this->editingFolderVisibleFor = $isHidden
            ? $this->enforceLockedVisibility($this->editingFolderVisibleFor)
            : [];
    }

    public function updatedEditingFolderVisibleFor(): void
    {
        if (! $this->editingFolderHidden) {
            $this->editingFolderVisibleFor = [];

            return;
        }

        $this->editingFolderVisibleFor = $this->enforceLockedVisibility($this->editingFolderVisibleFor);
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

        $folderName = $this->visibleFoldersQuery()->whereKey($this->newProjectFolderId)->value('name');

        return is_string($folderName) && $folderName !== '' ? $folderName : 'папку';
    }

    public function folderVisibilityAccessLevelLabel(int $accessLevel): string
    {
        return match ($accessLevel) {
            User::ACCESS_LEVEL_SUPERADMIN => 'Суперадмин',
            User::ACCESS_LEVEL_ADMIN => 'Админ',
            default => 'Пользователь',
        };
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

    private function viewer(): ?User
    {
        $viewer = auth()->user();

        return $viewer instanceof User ? $viewer : null;
    }

    /**
     * @return array<int, int>
     */
    private function visibleFolderIds(): array
    {
        return $this->visibleFoldersQuery()
            ->pluck('id')
            ->map(fn (mixed $folderId): int => (int) $folderId)
            ->all();
    }

    private function defaultExpandedFolderId(): ?int
    {
        $visibleFolderIds = $this->visibleFolderIds();

        return count($visibleFolderIds) === 1
            ? (int) $visibleFolderIds[0]
            : null;
    }

    private function visibleFoldersQuery(): Builder
    {
        return Folder::query()
            ->visibleTo($this->viewer())
            ->orderBy('name');
    }

    private function hydrateFolderVisibilityOptions(): void
    {
        $viewer = $this->viewer();

        $this->folderVisibilityUsers = User::query()
            ->orderByDesc('access_level')
            ->orderBy('name')
            ->get(['id', 'name', 'access_level'])
            ->map(static fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'access_level' => (int) $user->access_level,
            ])
            ->all();

        $lockedIds = collect($this->folderVisibilityUsers)
            ->filter(static fn (array $user): bool => (int) $user['access_level'] === User::ACCESS_LEVEL_SUPERADMIN)
            ->pluck('id')
            ->map(static fn (mixed $userId): int => (int) $userId)
            ->values();

        if ($viewer instanceof User) {
            $lockedIds->push((int) $viewer->id);
        }

        $this->lockedFolderVisibilityUserIds = $lockedIds
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int|string>  $visibleFor
     * @return array<int, int>
     */
    private function normalizeVisibleFor(array $visibleFor): array
    {
        $availableUserIds = collect($this->folderVisibilityUsers)
            ->pluck('id')
            ->map(static fn (mixed $userId): int => (int) $userId)
            ->all();

        return collect($visibleFor)
            ->map(static fn (mixed $userId): int => (int) $userId)
            ->filter(static fn (int $userId): bool => $userId > 0 && in_array($userId, $availableUserIds, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int|string>  $visibleFor
     * @return array<int, int>
     */
    private function enforceLockedVisibility(array $visibleFor): array
    {
        return collect($this->normalizeVisibleFor($visibleFor))
            ->merge($this->lockedFolderVisibilityUserIds)
            ->map(static fn (mixed $userId): int => (int) $userId)
            ->unique()
            ->values()
            ->all();
    }

    public function render(): View
    {
        $visibleFolderIds = $this->visibleFolderIds();

        if ($this->expandedFolderId !== null && ! in_array($this->expandedFolderId, $visibleFolderIds, true)) {
            $this->expandedFolderId = count($visibleFolderIds) === 1
                ? (int) $visibleFolderIds[0]
                : null;
        }

        return view('pages.projects-page', [
            'folders' => app(ProjectFoldersQuery::class)->get($this->viewer()),
            'pipelineVersionOptions' => $this->pipelineVersionOptions,
        ])->layout('layouts.app', [
            'title' => 'Проекты | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [
                ['label' => 'Проекты', 'current' => true],
            ],
        ]);
    }
}
