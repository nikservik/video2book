<?php

namespace App\Livewire\ProjectShow\Modals;

use App\Actions\Pipeline\GetPipelineVersionOptionsAction;
use App\Actions\Project\UpdateProjectNameAction;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class RenameProjectModal extends Component
{
    public int $projectId;

    public bool $show = false;

    public string $editableProjectName = '';

    public string $editableProjectReferer = '';

    public ?int $editableProjectDefaultPipelineVersionId = null;

    /**
     * @var array<int, array{id:int,label:string,description:string|null}>
     */
    public array $pipelineVersionOptions = [];

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;
    }

    #[On('project-show:rename-project-modal-open')]
    public function open(): void
    {
        $project = $this->project();

        $this->pipelineVersionOptions = app(GetPipelineVersionOptionsAction::class)->handle();
        $availablePipelineVersionIds = $this->availablePipelineVersionIds();

        $this->resetErrorBag();
        $this->editableProjectName = $project->name;
        $this->editableProjectReferer = $project->referer ?? '';
        $this->editableProjectDefaultPipelineVersionId = in_array((int) $project->default_pipeline_version_id, $availablePipelineVersionIds, true)
            ? (int) $project->default_pipeline_version_id
            : null;

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
        $this->dispatch('project-show:modal-closed');
    }

    public function saveProject(UpdateProjectNameAction $updateProjectNameAction): void
    {
        $availablePipelineVersionIds = $this->availablePipelineVersionIds();

        $normalizedData = [
            'editableProjectName' => $this->editableProjectName,
            'editableProjectReferer' => blank($this->editableProjectReferer) ? null : trim($this->editableProjectReferer),
            'editableProjectDefaultPipelineVersionId' => $this->editableProjectDefaultPipelineVersionId,
        ];

        $validated = validator($normalizedData, [
            'editableProjectName' => ['required', 'string', 'max:255'],
            'editableProjectReferer' => ['nullable', 'url', 'starts_with:https://'],
            'editableProjectDefaultPipelineVersionId' => ['nullable', 'integer', Rule::in($availablePipelineVersionIds)],
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
            project: $this->project(),
            name: $newName,
            referer: $newReferer,
            defaultPipelineVersionId: $newDefaultPipelineVersionId,
        );

        $this->dispatch('project-show:project-updated');
        $this->close();
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

    private function project(): Project
    {
        return Project::query()->findOrFail($this->projectId);
    }

    public function render(): View
    {
        return view('project-show.modals.rename-project-modal', [
            'pipelineVersionOptions' => $this->pipelineVersionOptions,
        ]);
    }
}
