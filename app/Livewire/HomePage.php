<?php

namespace App\Livewire;

use App\Actions\Project\RecalculateProjectLessonsAudioDurationAction;
use App\Services\Project\RecentProjectsQuery;
use App\Support\AudioDurationLabelFormatter;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class HomePage extends Component
{
    public function projectDurationLabel(?array $settings): string
    {
        return app(AudioDurationLabelFormatter::class)->format(
            data_get($settings, RecalculateProjectLessonsAudioDurationAction::PROJECT_TOTAL_DURATION_SETTING_KEY)
        ) ?? '—';
    }

    public function render(): View
    {
        return view('pages.home-page', [
            'recentProjects' => app(RecentProjectsQuery::class)->get(viewer: auth()->user()),
        ])->layout('layouts.app', [
            'title' => 'Главная | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [],
        ]);
    }
}
