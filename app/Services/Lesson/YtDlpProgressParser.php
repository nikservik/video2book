<?php

namespace App\Services\Lesson;

class YtDlpProgressParser
{
    /**
     * @return array<int, float>
     */
    public function extractPercentages(string $buffer): array
    {
        $count = preg_match_all('/\[download\]\s+(?<percentage>\d+(?:\.\d+)?)%/i', $buffer, $matches);

        if ($count === false || $count === 0) {
            return [];
        }

        return array_map(
            static fn (string $percentage): float => max(0.0, min(100.0, (float) $percentage)),
            $matches['percentage']
        );
    }
}
