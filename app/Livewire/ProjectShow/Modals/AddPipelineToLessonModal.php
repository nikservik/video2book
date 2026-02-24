<?php

namespace App\Livewire\ProjectShow\Modals;

use App\Actions\Pipeline\GetPipelineVersionOptionsAction;
use App\Actions\Project\AddPipelineVersionToLessonAction;
use App\Models\Project;
use App\Services\Project\ProjectDetailsQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class AddPipelineToLessonModal extends Component
{
    public int $projectId;

    public bool $show = false;

    public ?int $addingPipelineLessonId = null;

    public string $addingPipelineLessonName = '';

    public ?int $addingPipelineVersionId = null;

    /**
     * @var array<int, int>
     */
    public array $existingLessonPipelineVersionIds = [];

    /**
     * @var array<int, array{id:int,label:string,description:string|null}>
     */
    public array $pipelineVersionOptions = [];

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;
        $this->pipelineVersionOptions = app(GetPipelineVersionOptionsAction::class)->handle();
    }

    #[On('project-show:add-pipeline-to-lesson-modal-open')]
    public function open(int $lessonId): void
    {
        $this->pipelineVersionOptions = app(GetPipelineVersionOptionsAction::class)->handle();

        $project = $this->projectWithDetails();
        $lesson = $project->lessons->firstWhere('id', $lessonId);

        abort_if($lesson === null, 404);

        $this->resetErrorBag();

        $this->addingPipelineLessonId = $lesson->id;
        $this->addingPipelineLessonName = $lesson->name;
        $this->existingLessonPipelineVersionIds = $lesson->pipelineRuns
            ->pluck('pipeline_version_id')
            ->filter()
            ->map(fn (mixed $pipelineVersionId): int => (int) $pipelineVersionId)
            ->values()
            ->all();

        $this->addingPipelineVersionId = $this->resolvePreferredPipelineVersionId($project->default_pipeline_version_id);

        if ($this->show) {
            return;
        }

        $this->show = true;
        $this->dispatch('project-show:modal-opened');
    }

    public function close(): void
    {
        if (! $this->show) {
            return;
        }

        $this->show = false;
        $this->addingPipelineLessonId = null;
        $this->addingPipelineLessonName = '';
        $this->addingPipelineVersionId = null;
        $this->existingLessonPipelineVersionIds = [];

        $this->dispatch('project-show:modal-closed');
    }

    public function addPipelineToLesson(AddPipelineVersionToLessonAction $action): void
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
            'addingPipelineVersionId' => 'версия шаблона',
        ])->validate();

        $action->handle(
            project: $this->project(),
            lessonId: $this->addingPipelineLessonId,
            pipelineVersionId: (int) $validated['addingPipelineVersionId'],
        );

        $this->dispatch('project-show:project-updated');
        $this->close();
    }

    public function updatedAddingPipelineVersionId($value): void
    {
        $this->addingPipelineVersionId = $value === '' || $value === null ? null : (int) $value;
    }

    /**
     * @return array<int, array{id:int,label:string,description:string|null}>
     */
    public function getAddPipelineVersionOptionsProperty(): array
    {
        return collect($this->pipelineVersionOptions)
            ->reject(fn (array $option): bool => in_array($option['id'], $this->existingLessonPipelineVersionIds, true))
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

    private function resolvePreferredPipelineVersionId(?int $defaultPipelineVersionId): ?int
    {
        if ($defaultPipelineVersionId !== null) {
            $hasDefaultOption = collect($this->addPipelineVersionOptions)
                ->contains(fn (array $option): bool => $option['id'] === $defaultPipelineVersionId);

            if ($hasDefaultOption) {
                return $defaultPipelineVersionId;
            }
        }

        return data_get($this->addPipelineVersionOptions, '0.id');
    }

    private function project(): Project
    {
        return Project::query()->findOrFail($this->projectId);
    }

    private function projectWithDetails(): Project
    {
        return app(ProjectDetailsQuery::class)->get($this->project());
    }

    public function render(): View
    {
        return view('project-show.modals.add-pipeline-to-lesson-modal');
    }
}
