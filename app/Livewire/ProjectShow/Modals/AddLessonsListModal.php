<?php

namespace App\Livewire\ProjectShow\Modals;

use App\Actions\Project\AddLessonsListToProjectAction;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class AddLessonsListModal extends Component
{
    public int $projectId;

    public bool $show = false;

    public string $newLessonsList = '';

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;
    }

    #[On('project-show:add-lessons-list-modal-open')]
    public function open(): void
    {
        $this->resetErrorBag();
        $this->newLessonsList = '';

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

    public function addLessons(AddLessonsListToProjectAction $action): void
    {
        $normalized = [
            'newLessonsList' => blank($this->newLessonsList) ? null : $this->newLessonsList,
        ];

        $validated = validator($normalized, [
            'newLessonsList' => ['required', 'string'],
        ], [], [
            'newLessonsList' => 'список уроков',
        ])->validate();

        $action->handle(
            project: $this->project(),
            lessonsList: $validated['newLessonsList'],
        );

        $this->dispatch('project-show:project-updated');
        $this->close();
    }

    private function project(): Project
    {
        return Project::query()->findOrFail($this->projectId);
    }

    public function render(): View
    {
        return view('project-show.modals.add-lessons-list-modal');
    }
}
