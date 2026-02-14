<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class CreatePipelinePage extends Component
{
    public function render(): View
    {
        return view('pages.create-pipeline-page')->layout('layouts.app', [
            'title' => 'Добавить пайплайн | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [
                ['label' => 'Пайплайны', 'url' => route('pipelines.index')],
                ['label' => 'Добавить пайплайн', 'current' => true],
            ],
        ]);
    }
}
