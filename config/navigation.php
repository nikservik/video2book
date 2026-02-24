<?php

return [
    'main' => [
        [
            'key' => 'home',
            'label' => 'Главная',
            'route' => 'home',
            'active' => 'home',
            'min_access_level' => 0,
        ],
        [
            'key' => 'projects',
            'label' => 'Проекты',
            'route' => 'projects.index',
            'active' => 'projects.*',
            'min_access_level' => 0,
        ],
        [
            'key' => 'pipelines',
            'label' => 'Пайплайны',
            'route' => 'pipelines.index',
            'active' => 'pipelines.*',
            'min_access_level' => 1,
        ],
        [
            'key' => 'users',
            'label' => 'Пользователи',
            'route' => 'users.index',
            'active' => 'users.*',
            'min_access_level' => 1,
        ],
    ],
];
