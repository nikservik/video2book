<?php

namespace App\Services\Llm;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class AnthropicRateLimiter
{
    private const LIMIT_TOKENS_PER_MINUTE = 10000;
    private const WINDOW_SECONDS = 60;
    private const DEFAULT_RETRY_SECONDS = 10;
    private const CACHE_KEY = 'anthropic_haiku_token_window';

    public function estimateOutputTokens(string $input): int
    {
        $length = mb_strlen($input, 'UTF-8');
        // Примерная оценка: ~4 символа на токен.
        return max(1, (int) ceil($length / 4));
    }

    public function reserve(int $estimatedTokens): RateLimitReservation
    {
        [$entries, $now] = $this->loadWindow();
        $current = $this->sumTokens($entries);

        if ($current + $estimatedTokens > self::LIMIT_TOKENS_PER_MINUTE) {
            $retry = $this->calculateRetryAfter($entries, $estimatedTokens, $now);

            return new RateLimitReservation(false, $retry);
        }

        $reservationId = (string) Str::uuid();
        $entries[$reservationId] = [
            'time' => $now,
            'tokens' => $estimatedTokens,
        ];

        $this->storeWindow($entries);

        return new RateLimitReservation(true, 0, $reservationId);
    }

    public function finalize(string $reservationId, int $actualTokens): void
    {
        [$entries] = $this->loadWindow();

        if (! isset($entries[$reservationId])) {
            return;
        }

        $entries[$reservationId]['tokens'] = $actualTokens;

        $this->storeWindow($entries);
    }

    public function release(string $reservationId): void
    {
        [$entries] = $this->loadWindow();

        if (! isset($entries[$reservationId])) {
            return;
        }

        unset($entries[$reservationId]);

        $this->storeWindow($entries);
    }

    /**
     * @return array{0: array<string, array{time:int,tokens:int}>, 1: int}
     */
    private function loadWindow(): array
    {
        $now = now()->getTimestamp();
        $entries = Cache::get(self::CACHE_KEY, []);

        $entries = array_filter($entries, static function (array $entry) use ($now): bool {
            $entryTime = Arr::get($entry, 'time', 0);

            return $entryTime >= ($now - self::WINDOW_SECONDS);
        });

        if ($entries !== []) {
            $this->storeWindow($entries);
        }

        return [$entries, $now];
    }

    /**
     * @param  array<string, array{time:int,tokens:int}>  $entries
     */
    private function storeWindow(array $entries): void
    {
        Cache::put(self::CACHE_KEY, $entries, now()->addSeconds(self::WINDOW_SECONDS * 2));
    }

    /**
     * @param  array<string, array{time:int,tokens:int}>  $entries
     */
    private function sumTokens(array $entries): int
    {
        return array_reduce($entries, static function (int $carry, array $entry): int {
            return $carry + (int) ($entry['tokens'] ?? 0);
        }, 0);
    }

    /**
     * @param  array<string, array{time:int,tokens:int}>  $entries
     */
    private function calculateRetryAfter(array $entries, int $requestedTokens, int $now): int
    {
        if ($entries === []) {
            return self::DEFAULT_RETRY_SECONDS;
        }

        uasort($entries, static function (array $a, array $b): int {
            return ($a['time'] ?? 0) <=> ($b['time'] ?? 0);
        });
        $remaining = $this->sumTokens($entries);

        foreach ($entries as $entry) {
            $remaining -= (int) ($entry['tokens'] ?? 0);

            if ($remaining + $requestedTokens <= self::LIMIT_TOKENS_PER_MINUTE) {
                $retryAfter = max(1, ($entry['time'] + self::WINDOW_SECONDS) - $now);

                return $retryAfter;
            }
        }

        return self::DEFAULT_RETRY_SECONDS;
    }
}
