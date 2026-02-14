<?php

namespace App\Services\Home;

class DevelopmentQueueWidgetDataProvider
{
    /**
     * @return array{title: string, description: string, placeholder: string}
     */
    public function get(): array
    {
        return [
            'title' => 'Очередь разработки',
            'description' => 'Здесь будет виджет состояния очереди разработки.',
            'placeholder' => 'Место зарезервировано.',
        ];
    }
}
