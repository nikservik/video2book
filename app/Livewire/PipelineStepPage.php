<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class PipelineStepPage extends Component
{
    public string $pipeline = '';

    public string $step = '';

    public function mount(string $pipeline, string $step): void
    {
        $this->pipeline = $pipeline;
        $this->step = $step;
    }

    public function render(): View
    {
        return view('pages.pipeline-step-page', [
            'pipeline' => $this->pipeline,
            'step' => $this->step,
        ])->layout('layouts.app', [
            'title' => 'Шаг пайплайна | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [
                ['label' => 'Пайплайны', 'url' => route('pipelines.index')],
                ['label' => $this->pipeline],
                ['label' => $this->step, 'current' => true],
            ],
        ]);
    }
}
