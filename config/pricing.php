<?php

return [
    'whisper' => [
        'price_per_minute' => 0.006,
    ],

    'providers' => [
        'openai' => [
            'models' => [
                'gpt-5-nano' => [
                    'input' => 0.00000005,
                    'output' => 0.0000004,
                ],
                'gpt-5-mini' => [
                    'input' => 0.00000025,
                    'output' => 0.000002,
                ],
                'gpt-5.1' => [
                    'input' => 0.00000125,
                    'output' => 0.00001,
                ],
                'gpt-5.2' => [
                    'input' => 0.00000175,
                    'output' => 0.000014,
                ],
            ],
        ],

        'anthropic' => [
            'models' => [
                'claude-haiku-4-5' => [
                    'input' => 0.000001,
                    'output' => 0.000005,
                ],
                'claude-sonnet-4-5' => [
                    'input' => 0.000003,
                    'output' => 0.000015,
                ],
            ],
        ],

        'gemini' => [
            'models' => [
                'gemini-3-flash-preview' => [
                    'input' => [
                        'text' => 0.0000005,
                        'audio' => 0.0000010,
                    ],
                    'output' => 0.0000030,
                ],
                'gemini-2.5-flash' => [
                    'input' => [
                        'text' => 0.0000003,
                        'audio' => 0.0000010,
                    ],
                    'output' => 0.0000025,
                ],
                'gemini-2.5-pro' => [
                    'input' => [
                        'text' => 0.00000125,
                    ],
                    'output' => 0.00001,
                ],
            ],
        ],
    ],
];
