<?php

namespace App\Livewire\Widgets;

use App\Services\Home\QueueWidgetDataProvider;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class QueueWidget extends Component
{
    /**
     * @var array<int, string>
     */
    public array $expandedTaskKeys = [];

    public function toggleTask(string $taskKey): void
    {
        if (in_array($taskKey, $this->expandedTaskKeys, true)) {
            $this->expandedTaskKeys = array_values(
                array_filter(
                    $this->expandedTaskKeys,
                    static fn (string $expandedTaskKey): bool => $expandedTaskKey !== $taskKey
                )
            );

            return;
        }

        $this->expandedTaskKeys[] = $taskKey;
    }

    public function render(): View
    {
        return view('widgets.queue-widget', [
            'widget' => app(QueueWidgetDataProvider::class)->get(),
        ]);
    }
}
