<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class PipelinesPage extends Component
{
    public function render(): View
    {
        return view('pages.pipelines-page')->layout('layouts.app', [
            'title' => 'Пайплайны | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [
                ['label' => 'Пайплайны', 'current' => true],
            ],
        ]);
    }
}
