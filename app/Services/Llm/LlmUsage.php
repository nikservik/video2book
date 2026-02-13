<?php

namespace App\Services\Llm;

final class LlmUsage
{
    public function __construct(
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly float $cost = 0.0,
        public readonly array $meta = [],
    ) {}

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}
