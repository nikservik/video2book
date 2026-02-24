<?php

namespace App\Livewire;

use App\Actions\Pipeline\GetPipelineVersionOptionsAction;
use App\Actions\Project\CreateProjectFromLessonsListAction;
use App\Services\Project\PaginatedProjectsQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ProjectsPage extends Component
{
    private const PER_PAGE = 15;

    public bool $showCreateProjectModal = false;

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
    }

    public function openCreateProjectModal(): void
    {
        $this->resetErrorBag();
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
            'newProjectName' => $this->newProjectName,
            'newProjectReferer' => blank($this->newProjectReferer) ? null : trim($this->newProjectReferer),
            'newProjectDefaultPipelineVersionId' => $this->newProjectDefaultPipelineVersionId,
            'newProjectLessonsList' => blank($this->newProjectLessonsList) ? null : $this->newProjectLessonsList,
        ];

        $validated = validator($normalizedData, [
            'newProjectName' => ['required', 'string', 'max:255'],
            'newProjectReferer' => ['nullable', 'url', 'starts_with:https://'],
            'newProjectDefaultPipelineVersionId' => ['nullable', 'integer', Rule::in($availablePipelineVersionIds)],
            'newProjectLessonsList' => ['nullable', 'string'],
        ], [], [
            'newProjectName' => 'название проекта',
            'newProjectReferer' => 'referer',
            'newProjectDefaultPipelineVersionId' => 'версия шаблона по умолчанию',
            'newProjectLessonsList' => 'список уроков',
        ])->validate();

        $action->handle(
            projectName: $validated['newProjectName'],
            referer: $validated['newProjectReferer'],
            defaultPipelineVersionId: $validated['newProjectDefaultPipelineVersionId'] === null
                ? null
                : (int) $validated['newProjectDefaultPipelineVersionId'],
            lessonsList: $validated['newProjectLessonsList'],
        );

        $this->closeCreateProjectModal();
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
            'projects' => app(PaginatedProjectsQuery::class)->get(self::PER_PAGE),
            'pipelineVersionOptions' => $this->pipelineVersionOptions,
        ])->layout('layouts.app', [
            'title' => 'Проекты | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [
                ['label' => 'Проекты', 'current' => true],
            ],
        ]);
    }
}
