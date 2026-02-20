<?php

namespace App\Services\Lesson;

use App\Jobs\DownloadLessonAudioJob;
use App\Jobs\NormalizeUploadedLessonAudioJob;
use App\Models\Lesson;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

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

    public function startUploadedAudioNormalization(Lesson $lesson, UploadedFile $audioFile): Lesson
    {
        $settings = $lesson->settings ?? [];
        $settings['downloading'] = true;
        $settings['download_status'] = 'queued';
        $settings['download_progress'] = 0;
        $settings['download_error'] = null;
        $settings['download_source'] = 'uploaded_audio';

        $lesson->forceFill(['settings' => $settings])->save();

        $extension = strtolower($audioFile->getClientOriginalExtension());
        $tempFilename = $extension !== '' ? 'uploaded.'.$extension : 'uploaded.audio';
        $tempDirectory = 'downloader/'.$lesson->id.'/'.Str::uuid()->toString();
        $storedPath = $audioFile->storeAs(
            path: $tempDirectory,
            name: $tempFilename,
            options: ['disk' => 'local'],
        );

        if (! is_string($storedPath) || $storedPath === '') {
            throw new \RuntimeException('Не удалось сохранить загруженный аудиофайл.');
        }

        NormalizeUploadedLessonAudioJob::dispatch($lesson->id, $storedPath);

        return $lesson->fresh('project');
    }
}
