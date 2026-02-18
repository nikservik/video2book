<?php

namespace App\Livewire\Widgets;

use App\Services\Home\QueueWidgetDataProvider;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class QueueWidget extends Component
{
    private const MAX_VISIBLE_ITEMS = 5;

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
        $widget = app(QueueWidgetDataProvider::class)->get();
        $visibleItems = array_slice($widget['items'], 0, self::MAX_VISIBLE_ITEMS);

        return view('widgets.queue-widget', [
            'widget' => $widget,
            'visibleItems' => $visibleItems,
            'hiddenItemsCount' => max(0, count($widget['items']) - count($visibleItems)),
        ]);
    }
}
