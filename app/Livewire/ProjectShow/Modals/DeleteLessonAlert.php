<?php

namespace App\Livewire\ProjectShow\Modals;

use App\Actions\Project\DeleteProjectLessonAction;
use App\Models\Project;
use App\Services\Project\ProjectDetailsQuery;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class DeleteLessonAlert extends Component
{
    public int $projectId;

    public bool $show = false;

    public ?int $deletingLessonId = null;

    public string $deletingLessonName = '';

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;
    }

    #[On('project-show:delete-lesson-alert-open')]
    public function open(int $lessonId): void
    {
        $lesson = $this->projectWithDetails()->lessons->firstWhere('id', $lessonId);

        abort_if($lesson === null, 404);

        $this->deletingLessonId = $lesson->id;
        $this->deletingLessonName = $lesson->name;

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
        $this->deletingLessonId = null;
        $this->deletingLessonName = '';

        $this->dispatch('project-show:modal-closed');
    }

    public function deleteLesson(DeleteProjectLessonAction $deleteProjectLessonAction): void
    {
        abort_if($this->deletingLessonId === null, 422, 'Урок для удаления не выбран.');

        $deleteProjectLessonAction->handle($this->project(), $this->deletingLessonId);

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
        return view('project-show.modals.delete-lesson-alert');
    }
}
