<?php

namespace App\Livewire\ProjectShow\Modals;

use App\Actions\Project\DeleteProjectPipelineRunAction;
use App\Models\Project;
use App\Services\Project\ProjectDetailsQuery;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class DeleteRunAlert extends Component
{
    public int $projectId;

    public bool $show = false;

    public ?int $deletingRunId = null;

    public string $deletingRunLabel = '';

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;
    }

    #[On('project-show:delete-run-alert-open')]
    public function open(int $pipelineRunId): void
    {
        $pipelineRun = $this->projectWithDetails()->lessons
            ->flatMap(fn ($lesson) => $lesson->pipelineRuns)
            ->firstWhere('id', $pipelineRunId);

        abort_if($pipelineRun === null, 404);

        $this->deletingRunId = $pipelineRun->id;
        $this->deletingRunLabel = sprintf(
            '%s • v%s',
            $pipelineRun->pipelineVersion?->title ?? 'Без названия',
            (string) ($pipelineRun->pipelineVersion?->version ?? '—')
        );

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
        $this->deletingRunId = null;
        $this->deletingRunLabel = '';

        $this->dispatch('project-show:modal-closed');
    }

    public function deleteRun(DeleteProjectPipelineRunAction $deleteProjectPipelineRunAction): void
    {
        abort_if($this->deletingRunId === null, 422, 'Прогон для удаления не выбран.');

        $deleteProjectPipelineRunAction->handle($this->project(), $this->deletingRunId);

        $this->dispatch('project-show:project-updated');
        $this->close();
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
        return view('project-show.modals.delete-run-alert');
    }
}
