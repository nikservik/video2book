<?php

namespace App\Livewire;

use App\Services\Project\PaginatedProjectsQuery;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ProjectsPage extends Component
{
    private const int PER_PAGE = 15;

    public function render(): View
    {
        return view('pages.projects-page', [
            'projects' => app(PaginatedProjectsQuery::class)->get(self::PER_PAGE),
        ])->layout('layouts.app', [
            'title' => 'Проекты | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [
                ['label' => 'Проекты', 'current' => true],
            ],
        ]);
    }
}
