<?php

namespace App\Support;

class AudioDurationLabelFormatter
{
    public function format(mixed $durationSeconds): ?string
    {
        if (! is_numeric($durationSeconds)) {
            return null;
        }

        $durationSeconds = (int) $durationSeconds;

        if ($durationSeconds <= 0) {
            return null;
        }

        $totalMinutes = (int) round($durationSeconds / 60);
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        if ($hours <= 0) {
            return $minutes.'м';
        }

        return $hours.'ч '.$minutes.'м';
    }
}
