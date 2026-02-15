<?php

namespace App\Livewire\ProjectShow\Modals;

use App\Actions\Project\DeleteProjectAction;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class DeleteProjectAlert extends Component
{
    public int $projectId;

    public bool $show = false;

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;
    }

    #[On('project-show:delete-project-alert-open')]
    public function open(): void
    {
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

    public function deleteProject(DeleteProjectAction $deleteProjectAction): void
    {
        $deleteProjectAction->handle($this->project());

        $this->redirectRoute('projects.index', navigate: true);
    }

    private function project(): Project
    {
        return Project::query()->findOrFail($this->projectId);
    }

    public function render(): View
    {
        return view('project-show.modals.delete-project-alert');
    }
}
