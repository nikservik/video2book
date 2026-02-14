<?php

namespace App\Livewire;

use App\Services\Project\RecentProjectsQuery;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class HomePage extends Component
{
    public function render(): View
    {
        return view('pages.home-page', [
            'recentProjects' => app(RecentProjectsQuery::class)->get(),
        ])->layout('layouts.app', [
            'title' => 'Главная | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [],
        ]);
    }
}
