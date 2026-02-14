<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class ProjectLessonPage extends Component
{
    public string $project = '';

    public string $lesson = '';

    public function mount(string $project, string $lesson): void
    {
        $this->project = $project;
        $this->lesson = $lesson;
    }

    public function render(): View
    {
        return view('pages.project-lesson-page', [
            'project' => $this->project,
            'lesson' => $this->lesson,
        ])->layout('layouts.app', [
            'title' => 'Урок проекта | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [
                ['label' => 'Проекты', 'url' => route('projects.index')],
                ['label' => $this->project, 'url' => route('projects.show', ['project' => $this->project])],
                ['label' => $this->lesson, 'current' => true],
            ],
        ]);
    }
}
