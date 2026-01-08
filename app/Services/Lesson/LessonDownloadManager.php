<?php

namespace App\Services\Lesson;

use App\Jobs\DownloadLessonAudioJob;
use App\Models\Lesson;

class LessonDownloadManager
{
    public function startDownload(Lesson $lesson, string $url): Lesson
    {
        $settings = $lesson->settings ?? [];
        $settings['downloading'] = true;
        $settings['download_status'] = 'queued';
        $settings['download_progress'] = 0;
        $settings['download_error'] = null;
        $settings['download_source'] = $url;

        $lesson->forceFill(['settings' => $settings])->save();

        DownloadLessonAudioJob::dispatch($lesson->id, $url);

        return $lesson->fresh('project');
    }
}
