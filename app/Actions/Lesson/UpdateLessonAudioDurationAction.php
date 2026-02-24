<?php

namespace App\Actions\Lesson;

use App\Models\Lesson;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Throwable;

class UpdateLessonAudioDurationAction
{
    public const LESSON_DURATION_SETTING_KEY = 'audio_duration_seconds';

    public function handle(Lesson $lesson): ?int
    {
        $audioPath = trim((string) $lesson->source_filename);

        if ($audioPath === '') {
            return null;
        }

        $durationSeconds = $this->resolveDurationSeconds($audioPath);

        $settings = $lesson->settings ?? [];
        $settings[self::LESSON_DURATION_SETTING_KEY] = $durationSeconds;

        $lesson->forceFill(['settings' => $settings])->save();

        return $durationSeconds;
    }

    private function resolveDurationSeconds(string $audioPath): ?int
    {
        try {
            $duration = FFMpeg::fromDisk('local')
                ->open($audioPath)
                ->getDurationInSeconds();
        } catch (Throwable) {
            return null;
        }

        return $duration > 0 ? (int) $duration : null;
    }
}
