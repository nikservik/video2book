<?php

namespace App\Livewire;

use App\Services\Pipeline\PaginatedPipelinesQuery;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PipelinesPage extends Component
{
    private const PER_PAGE = 15;

    public function render(): View
    {
        return view('pages.pipelines-page', [
            'pipelines' => app(PaginatedPipelinesQuery::class)->get(self::PER_PAGE),
        ])->layout('layouts.app', [
            'title' => 'Пайплайны | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [
                ['label' => 'Пайплайны', 'current' => true],
            ],
        ]);
    }
}
