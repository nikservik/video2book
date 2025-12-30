<?php

namespace App\Services\Pipeline;

final class PipelineStepResult
{
    public function __construct(
        public readonly string $output,
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
        public readonly ?float $cost = null,
        public readonly array $meta = [],
    ) {
    }
}
