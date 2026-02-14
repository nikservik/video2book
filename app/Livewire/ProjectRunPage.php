<?php

namespace App\Livewire;

use App\Models\PipelineRun;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ProjectRunPage extends Component
{
    public Project $project;

    public PipelineRun $pipelineRun;

    public function mount(Project $project, PipelineRun $pipelineRun): void
    {
        $pipelineRun->loadMissing('lesson.project', 'pipelineVersion');

        abort_unless(
            $pipelineRun->lesson?->project_id === $project->id,
            404
        );

        $this->project = $project;
        $this->pipelineRun = $pipelineRun;
    }

    public function render(): View
    {
        return view('pages.project-run-page', [
            'project' => $this->project,
            'pipelineRun' => $this->pipelineRun,
        ])->layout('layouts.app', [
            'title' => 'Прогон #'.$this->pipelineRun->id.' | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [
                ['label' => 'Проекты', 'url' => route('projects.index')],
                ['label' => $this->project->name, 'url' => route('projects.show', $this->project)],
                ['label' => 'Прогон #'.$this->pipelineRun->id, 'current' => true],
            ],
        ]);
    }
}
