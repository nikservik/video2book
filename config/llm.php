<?php

return [
    'default_stream' => true,

    'request_timeout' => (int) env('LLM_REQUEST_TIMEOUT', 1800),

    'defaults' => [
        'anthropic' => [
            'max_tokens' => (int) env('LLM_ANTHROPIC_MAX_TOKENS', 64000),
        ],
    ],
];
