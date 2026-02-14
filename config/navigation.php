<?php

return [
    'main' => [
        [
            'key' => 'home',
            'label' => 'Главная',
            'route' => 'home',
            'active' => 'home',
        ],
        [
            'key' => 'projects',
            'label' => 'Проекты',
            'route' => 'projects.index',
            'active' => 'projects.*',
        ],
        [
            'key' => 'pipelines',
            'label' => 'Пайплайны',
            'route' => 'pipelines.index',
            'active' => 'pipelines.*',
        ],
    ],
];
