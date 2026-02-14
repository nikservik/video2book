<?php

namespace App\Livewire\Widgets;

use App\Services\Home\DevelopmentQueueWidgetDataProvider;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class DevelopmentQueueWidget extends Component
{
    public function render(): View
    {
        return view('widgets.development-queue-widget', [
            'widget' => app(DevelopmentQueueWidgetDataProvider::class)->get(),
        ]);
    }
}
