<?php

namespace App\Services\Llm;

final class RateLimitReservation
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $retryAfterSeconds,
        public readonly ?string $reservationId = null,
    ) {}
}
