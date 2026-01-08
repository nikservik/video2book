<?php

return [
    'default_stream' => true,

    'providers' => [
        'openai' => App\Services\Llm\Providers\OpenAiLlmProvider::class,
        'anthropic' => App\Services\Llm\Providers\AnthropicLlmProvider::class,
        'gemini' => App\Services\Llm\Providers\GeminiLlmProvider::class,
    ],

    'defaults' => [
        'anthropic' => [
            'max_tokens' => (int) env('LLM_ANTHROPIC_MAX_TOKENS', 64000),
        ],
    ],
];
