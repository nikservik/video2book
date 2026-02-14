<?php

namespace App\Livewire;

use App\Actions\Pipeline\GetPipelineVersionOptionsAction;
use App\Actions\Project\AddPipelineVersionToLessonAction;
use App\Actions\Project\CreateProjectLessonFromYoutubeAction;
use App\Actions\Project\DeleteProjectAction;
use App\Actions\Project\DeleteProjectLessonAction;
use App\Actions\Project\DeleteProjectPipelineRunAction;
use App\Actions\Project\UpdateProjectLessonNameAction;
use App\Actions\Project\UpdateProjectNameAction;
use App\Models\Project;
use App\Services\Project\ProjectDetailsQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ProjectShowPage extends Component
{
    public Project $project;

    public bool $showDeleteProjectAlert = false;

    public bool $showDeleteLessonAlert = false;

    public bool $showDeleteRunAlert = false;

    public bool $showRenameLessonModal = false;

    public bool $showRenameProjectModal = false;

    public bool $showCreateLessonModal = false;

    public bool $showAddPipelineToLessonModal = false;

    public string $editableProjectName = '';

    public string $editableProjectReferer = '';

    public ?int $editableProjectDefaultPipelineVersionId = null;

    public string $newLessonName = '';

    public string $newLessonYoutubeUrl = '';

    public ?int $newLessonPipelineVersionId = null;

    public ?int $deletingLessonId = null;

    public string $deletingLessonName = '';

    public ?int $deletingRunId = null;

    public string $deletingRunLabel = '';

    public ?int $editingLessonId = null;

    public string $editableLessonName = '';

    public ?int $addingPipelineLessonId = null;

    public string $addingPipelineLessonName = '';

    public ?int $addingPipelineVersionId = null;

    /**
     * @var array<int, array{id:int,label:string}>
     */
    public array $pipelineVersionOptions = [];

    public function mount(Project $project): void
    {
        $this->project = app(ProjectDetailsQuery::class)->get($project);
        $this->pipelineVersionOptions = app(GetPipelineVersionOptionsAction::class)->handle();
    }

    public function openCreateLessonModal(): void
    {
        $this->showDeleteProjectAlert = false;
        $this->showDeleteLessonAlert = false;
        $this->showDeleteRunAlert = false;
        $this->showRenameLessonModal = false;
        $this->showRenameProjectModal = false;
        $this->showAddPipelineToLessonModal = false;
        $this->resetErrorBag();

        $this->newLessonName = '';
        $this->newLessonYoutubeUrl = '';
        $this->newLessonPipelineVersionId = $this->resolvePreferredPipelineVersionId($this->pipelineVersionOptions);
        $this->showCreateLessonModal = true;
    }

    /**
     * @param  array<int, array{id:int,label:string}>  $pipelineVersionOptions
     */
    private function resolvePreferredPipelineVersionId(array $pipelineVersionOptions): ?int
    {
        $defaultPipelineVersionId = $this->project->default_pipeline_version_id;

        if ($defaultPipelineVersionId !== null) {
            $hasDefaultOption = collect($pipelineVersionOptions)
                ->contains(fn (array $option): bool => $option['id'] === $defaultPipelineVersionId);

            if ($hasDefaultOption) {
                return $defaultPipelineVersionId;
            }
        }

        return $pipelineVersionOptions[0]['id'] ?? null;
    }

    public function closeCreateLessonModal(): void
    {
        $this->showCreateLessonModal = false;
    }

    public function createLessonFromYoutube(CreateProjectLessonFromYoutubeAction $action): void
    {
        $validated = $this->validate([
            'newLessonName' => ['required', 'string', 'max:255'],
            'newLessonYoutubeUrl' => ['required', 'url', 'starts_with:https://'],
            'newLessonPipelineVersionId' => ['required', 'integer', 'exists:pipeline_versions,id'],
        ], [], [
            'newLessonName' => 'название урока',
            'newLessonYoutubeUrl' => 'ссылка на YouTube',
            'newLessonPipelineVersionId' => 'версия пайплайна',
        ]);

        $action->handle(
            $this->project,
            $validated['newLessonName'],
            $validated['newLessonYoutubeUrl'],
            (int) $validated['newLessonPipelineVersionId'],
        );

        $this->project = app(ProjectDetailsQuery::class)->get($this->project->fresh());
        $this->showCreateLessonModal = false;
    }

    public function updatedNewLessonPipelineVersionId($value): void
    {
        $this->newLessonPipelineVersionId = $value === '' || $value === null ? null : (int) $value;
    }

    public function getSelectedPipelineVersionLabelProperty(): string
    {
        return data_get(
            collect($this->pipelineVersionOptions)->firstWhere('id', $this->newLessonPipelineVersionId),
            'label',
            'Выберите версию'
        );
    }

    public function openAddPipelineToLessonModal(int $lessonId): void
    {
        $lesson = $this->project->lessons->firstWhere('id', $lessonId);

        abort_if($lesson === null, 404);

        $this->showCreateLessonModal = false;
        $this->showDeleteProjectAlert = false;
        $this->showDeleteLessonAlert = false;
        $this->showDeleteRunAlert = false;
        $this->showRenameLessonModal = false;
        $this->showRenameProjectModal = false;
        $this->resetErrorBag();

        $this->addingPipelineLessonId = $lesson->id;
        $this->addingPipelineLessonName = $lesson->name;
        $this->addingPipelineVersionId = $this->resolvePreferredPipelineVersionId($this->addPipelineVersionOptions);

        $this->showAddPipelineToLessonModal = true;
    }

    public function closeAddPipelineToLessonModal(): void
    {
        $this->showAddPipelineToLessonModal = false;
        $this->addingPipelineLessonId = null;
        $this->addingPipelineLessonName = '';
        $this->addingPipelineVersionId = null;
    }

    public function addPipelineToLesson(AddPipelineVersionToLessonAction $addPipelineVersionToLessonAction): void
    {
        abort_if($this->addingPipelineLessonId === null, 422, 'Урок для добавления версии не выбран.');

        $availableVersionIds = collect($this->addPipelineVersionOptions)
            ->pluck('id')
            ->all();

        $validated = validator([
            'addingPipelineVersionId' => $this->addingPipelineVersionId,
        ], [
            'addingPipelineVersionId' => ['required', 'integer', 'exists:pipeline_versions,id', Rule::in($availableVersionIds)],
        ], [], [
            'addingPipelineVersionId' => 'версия пайплайна',
        ])->validate();

        $addPipelineVersionToLessonAction->handle(
            project: $this->project,
            lessonId: $this->addingPipelineLessonId,
            pipelineVersionId: (int) $validated['addingPipelineVersionId'],
        );

        $this->project = app(ProjectDetailsQuery::class)->get($this->project->fresh());
        $this->closeAddPipelineToLessonModal();
    }

    public function updatedAddingPipelineVersionId($value): void
    {
        $this->addingPipelineVersionId = $value === '' || $value === null ? null : (int) $value;
    }

    /**
     * @return array<int, array{id:int,label:string}>
     */
    public function getAddPipelineVersionOptionsProperty(): array
    {
        $lesson = $this->project->lessons->firstWhere('id', $this->addingPipelineLessonId);

        if ($lesson === null) {
            return [];
        }

        $existingPipelineVersionIds = $lesson->pipelineRuns
            ->pluck('pipeline_version_id')
            ->filter()
            ->values()
            ->all();

        return collect($this->pipelineVersionOptions)
            ->reject(fn (array $option): bool => in_array($option['id'], $existingPipelineVersionIds, true))
            ->values()
            ->all();
    }

    public function getSelectedAddingPipelineVersionLabelProperty(): string
    {
        return data_get(
            collect($this->addPipelineVersionOptions)->firstWhere('id', $this->addingPipelineVersionId),
            'label',
            'Выберите версию'
        );
    }

    public function pipelineRunStatusLabel(?string $status): string
    {
        return match ($status) {
            'done' => 'Готово',
            'queued' => 'В очереди',
            'running' => 'Обработка',
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
            'failed' => 'inline-flex items-center rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700 dark:bg-red-400/10 dark:text-red-400',
            default => 'inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-400/10 dark:text-gray-400',
        };
    }

    public function openDeleteProjectAlert(): void
    {
        $this->showCreateLessonModal = false;
        $this->showDeleteLessonAlert = false;
        $this->showDeleteRunAlert = false;
        $this->showRenameLessonModal = false;
        $this->showRenameProjectModal = false;
        $this->showAddPipelineToLessonModal = false;
        $this->showDeleteProjectAlert = true;
    }

    public function closeDeleteProjectAlert(): void
    {
        $this->showDeleteProjectAlert = false;
    }

    public function openRenameProjectModal(): void
    {
        $this->showCreateLessonModal = false;
        $this->showDeleteLessonAlert = false;
        $this->showDeleteRunAlert = false;
        $this->showRenameLessonModal = false;
        $this->showDeleteProjectAlert = false;
        $this->showAddPipelineToLessonModal = false;
        $this->resetErrorBag();
        $this->editableProjectName = $this->project->name;
        $this->editableProjectReferer = $this->project->referer ?? '';
        $this->editableProjectDefaultPipelineVersionId = $this->project->default_pipeline_version_id;
        $this->showRenameProjectModal = true;
    }

    public function closeRenameProjectModal(): void
    {
        $this->showRenameProjectModal = false;
    }

    public function saveProject(UpdateProjectNameAction $updateProjectNameAction): void
    {
        $normalizedData = [
            'editableProjectName' => $this->editableProjectName,
            'editableProjectReferer' => blank($this->editableProjectReferer) ? null : trim($this->editableProjectReferer),
            'editableProjectDefaultPipelineVersionId' => $this->editableProjectDefaultPipelineVersionId,
        ];

        $validated = validator($normalizedData, [
            'editableProjectName' => ['required', 'string', 'max:255'],
            'editableProjectReferer' => ['nullable', 'url', 'starts_with:https://'],
            'editableProjectDefaultPipelineVersionId' => ['nullable', 'integer', 'exists:pipeline_versions,id'],
        ], [], [
            'editableProjectName' => 'название проекта',
            'editableProjectReferer' => 'referrer',
            'editableProjectDefaultPipelineVersionId' => 'версия пайплайна по умолчанию',
        ])->validate();

        $newName = trim($validated['editableProjectName']);
        $newReferer = $validated['editableProjectReferer'];
        $newDefaultPipelineVersionId = $validated['editableProjectDefaultPipelineVersionId'] === null
            ? null
            : (int) $validated['editableProjectDefaultPipelineVersionId'];

        $updateProjectNameAction->handle(
            project: $this->project,
            name: $newName,
            referer: $newReferer,
            defaultPipelineVersionId: $newDefaultPipelineVersionId,
        );

        $this->project->refresh();
        $this->editableProjectName = $newName;
        $this->editableProjectReferer = $newReferer ?? '';
        $this->editableProjectDefaultPipelineVersionId = $newDefaultPipelineVersionId;

        $this->showRenameProjectModal = false;
    }

    public function updatedEditableProjectDefaultPipelineVersionId($value): void
    {
        $this->editableProjectDefaultPipelineVersionId = $value === '' || $value === null ? null : (int) $value;
    }

    public function getSelectedEditableProjectDefaultPipelineVersionLabelProperty(): string
    {
        return data_get(
            collect($this->pipelineVersionOptions)->firstWhere('id', $this->editableProjectDefaultPipelineVersionId),
            'label',
            'Не выбрано'
        );
    }

    public function deleteProject(DeleteProjectAction $deleteProjectAction): void
    {
        $deleteProjectAction->handle($this->project);
        $this->redirectRoute('projects.index', navigate: true);
    }

    public function openDeleteLessonAlert(int $lessonId): void
    {
        $lesson = $this->project->lessons->firstWhere('id', $lessonId);

        abort_if($lesson === null, 404);

        $this->showCreateLessonModal = false;
        $this->showRenameProjectModal = false;
        $this->showRenameLessonModal = false;
        $this->showDeleteRunAlert = false;
        $this->showDeleteProjectAlert = false;
        $this->showAddPipelineToLessonModal = false;

        $this->deletingLessonId = $lesson->id;
        $this->deletingLessonName = $lesson->name;
        $this->showDeleteLessonAlert = true;
    }

    public function closeDeleteLessonAlert(): void
    {
        $this->showDeleteLessonAlert = false;
        $this->deletingLessonId = null;
        $this->deletingLessonName = '';
    }

    public function deleteLesson(DeleteProjectLessonAction $deleteProjectLessonAction): void
    {
        abort_if($this->deletingLessonId === null, 422, 'Урок для удаления не выбран.');

        $deleteProjectLessonAction->handle($this->project, $this->deletingLessonId);

        $this->project = app(ProjectDetailsQuery::class)->get($this->project->fresh());
        $this->closeDeleteLessonAlert();
    }

    public function openRenameLessonModal(int $lessonId): void
    {
        $lesson = $this->project->lessons->firstWhere('id', $lessonId);

        abort_if($lesson === null, 404);

        $this->showCreateLessonModal = false;
        $this->showDeleteProjectAlert = false;
        $this->showDeleteLessonAlert = false;
        $this->showDeleteRunAlert = false;
        $this->showRenameProjectModal = false;
        $this->showAddPipelineToLessonModal = false;
        $this->resetErrorBag();

        $this->editingLessonId = $lesson->id;
        $this->editableLessonName = $lesson->name;
        $this->showRenameLessonModal = true;
    }

    public function closeRenameLessonModal(): void
    {
        $this->showRenameLessonModal = false;
        $this->editingLessonId = null;
        $this->editableLessonName = '';
    }

    public function saveLessonName(UpdateProjectLessonNameAction $updateProjectLessonNameAction): void
    {
        abort_if($this->editingLessonId === null, 422, 'Урок для редактирования не выбран.');

        $validated = $this->validate([
            'editableLessonName' => ['required', 'string', 'max:255'],
        ], [], [
            'editableLessonName' => 'название урока',
        ]);

        $newName = trim($validated['editableLessonName']);

        $updateProjectLessonNameAction->handle(
            $this->project,
            $this->editingLessonId,
            $newName,
        );

        $this->project = app(ProjectDetailsQuery::class)->get($this->project->fresh());
        $this->closeRenameLessonModal();
    }

    public function openDeleteRunAlert(int $pipelineRunId): void
    {
        $pipelineRun = $this->project->lessons
            ->flatMap(fn ($lesson) => $lesson->pipelineRuns)
            ->firstWhere('id', $pipelineRunId);

        abort_if($pipelineRun === null, 404);

        $this->showCreateLessonModal = false;
        $this->showDeleteProjectAlert = false;
        $this->showDeleteLessonAlert = false;
        $this->showRenameProjectModal = false;
        $this->showRenameLessonModal = false;
        $this->showAddPipelineToLessonModal = false;

        $this->deletingRunId = $pipelineRun->id;
        $this->deletingRunLabel = sprintf(
            '%s • v%s',
            $pipelineRun->pipelineVersion?->title ?? 'Без названия',
            (string) ($pipelineRun->pipelineVersion?->version ?? '—')
        );
        $this->showDeleteRunAlert = true;
    }

    public function closeDeleteRunAlert(): void
    {
        $this->showDeleteRunAlert = false;
        $this->deletingRunId = null;
        $this->deletingRunLabel = '';
    }

    public function deleteRun(DeleteProjectPipelineRunAction $deleteProjectPipelineRunAction): void
    {
        abort_if($this->deletingRunId === null, 422, 'Прогон для удаления не выбран.');

        $deleteProjectPipelineRunAction->handle($this->project, $this->deletingRunId);

        $this->project = app(ProjectDetailsQuery::class)->get($this->project->fresh());
        $this->closeDeleteRunAlert();
    }

    public function render(): View
    {
        return view('pages.project-show-page', [
            'project' => $this->project,
            'pipelineVersionOptions' => $this->pipelineVersionOptions,
        ])->layout('layouts.app', [
            'title' => $this->project->name.' | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [
                ['label' => 'Проекты', 'url' => route('projects.index')],
                ['label' => $this->project->name, 'current' => true],
            ],
        ]);
    }
}
