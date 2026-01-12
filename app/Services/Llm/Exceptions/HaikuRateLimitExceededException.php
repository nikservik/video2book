<?php

namespace App\Services\Llm\Exceptions;

use RuntimeException;

final class HaikuRateLimitExceededException extends RuntimeException
{
    public function __construct(private readonly int $retryAfterSeconds)
    {
        parent::__construct('Anthropic Haiku rate limit reached.');
    }

    public function retryAfterSeconds(): int
    {
        return max(1, $this->retryAfterSeconds);
    }
}
