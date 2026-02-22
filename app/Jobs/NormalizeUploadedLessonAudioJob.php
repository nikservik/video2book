<?php

namespace App\Jobs;

use App\Models\Lesson;
use App\Services\Lesson\LessonDownloadService;
use App\Services\Pipeline\PipelineEventBroadcaster;
use App\Services\Pipeline\PipelineRunService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class NormalizeUploadedLessonAudioJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const QUEUE = 'downloads';

    public int $timeout = 1800;

    public function __construct(
        public readonly int $lessonId,
        public readonly string $uploadedAudioPath,
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function uniqueId(): string
    {
        return 'lesson-upload-normalize:'.$this->lessonId;
    }

    public function handle(
        LessonDownloadService $downloadService,
        PipelineRunService $pipelineRunService,
        PipelineEventBroadcaster $eventBroadcaster,
    ): void {
        $lesson = Lesson::query()->with('project')->findOrFail($this->lessonId);
        $lesson = $this->markAsRunning($lesson);
        $eventBroadcaster->downloadStarted($lesson);

        try {
            $normalizedResult = $downloadService->normalizeStoredAudio(
                lesson: $lesson,
                sourcePath: $this->uploadedAudioPath,
            );

            $lesson = $this->markAsCompleted(
                lesson: $lesson,
                path: $normalizedResult['path'],
                durationSeconds: $normalizedResult['duration_seconds'],
            );
            $eventBroadcaster->downloadCompleted($lesson);

            $pipelineRunService->dispatchQueuedRuns($lesson);
        } catch (Throwable $exception) {
            $lesson = $this->markAsFailed($lesson, $exception->getMessage());
            $eventBroadcaster->downloadFailed($lesson, $exception->getMessage());

            throw $exception;
        }
    }

    private function markAsRunning(Lesson $lesson): Lesson
    {
        $settings = $lesson->settings ?? [];
        $settings['downloading'] = true;
        $settings['download_status'] = 'running';
        $settings['download_progress'] = 0;
        $settings['download_error'] = null;

        $lesson->forceFill(['settings' => $settings])->save();

        return $lesson->fresh('project');
    }

    private function markAsCompleted(
        Lesson $lesson,
        string $path,
        ?int $durationSeconds,
    ): Lesson {
        $settings = $lesson->settings ?? [];
        $settings['downloading'] = false;
        $settings['download_status'] = 'completed';
        $settings['download_progress'] = 100;
        $settings['download_error'] = null;
        $settings['download_source'] = null;
        $settings['audio_duration_seconds'] = $durationSeconds;

        $lesson->forceFill([
            'settings' => $settings,
            'source_filename' => $path,
        ])->save();

        return $lesson->fresh('project');
    }

    private function markAsFailed(Lesson $lesson, string $message): Lesson
    {
        $settings = $lesson->settings ?? [];
        $settings['downloading'] = false;
        $settings['download_status'] = 'failed';
        $settings['download_progress'] = 0;
        $settings['download_error'] = $message;

        $lesson->forceFill(['settings' => $settings])->save();

        return $lesson->fresh('project');
    }
}
