<?php

namespace App\Livewire\ProjectShow\Modals;

use App\Actions\Project\UpdateProjectLessonNameAction;
use App\Models\Project;
use App\Services\Project\ProjectDetailsQuery;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class RenameLessonModal extends Component
{
    public int $projectId;

    public bool $show = false;

    public ?int $editingLessonId = null;

    public string $editableLessonName = '';

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;
    }

    #[On('project-show:rename-lesson-modal-open')]
    public function open(int $lessonId): void
    {
        $lesson = $this->projectWithDetails()->lessons->firstWhere('id', $lessonId);

        abort_if($lesson === null, 404);

        $this->resetErrorBag();
        $this->editingLessonId = $lesson->id;
        $this->editableLessonName = $lesson->name;

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
        $this->editingLessonId = null;
        $this->editableLessonName = '';

        $this->dispatch('project-show:modal-closed');
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
            $this->project(),
            $this->editingLessonId,
            $newName,
        );

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
        return view('project-show.modals.rename-lesson-modal');
    }
}
