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

class DownloadLessonAudioJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const QUEUE = 'downloads';

    private const PROGRESS_INTERVAL_SECONDS = 3;

    public int $timeout = 1800;

    public function __construct(public readonly int $lessonId, public readonly string $sourceUrl)
    {
        $this->onQueue(self::QUEUE);
    }

    public function uniqueId(): string
    {
        return 'lesson-download:'.$this->lessonId;
    }

    public function handle(
        LessonDownloadService $downloadService,
        PipelineRunService $pipelineRunService,
        PipelineEventBroadcaster $eventBroadcaster,
    ): void {
        $lesson = Lesson::query()->with('project')->findOrFail($this->lessonId);
        $lesson = $this->markAsRunning($lesson);
        $eventBroadcaster->downloadStarted($lesson);

        $lastProgressBroadcastAt = null;

        try {
            $downloadedPath = $downloadService->downloadAndNormalize($lesson, $this->sourceUrl, function (float $progress) use (&$lesson, $eventBroadcaster, &$lastProgressBroadcastAt): void {
                $progress = max(0, min(100, $progress));

                if (
                    $progress < 100
                    && $lastProgressBroadcastAt !== null
                    && $lastProgressBroadcastAt->diffInSeconds(now()) < self::PROGRESS_INTERVAL_SECONDS
                ) {
                    return;
                }

                $lesson = $this->updateProgress($lesson, $progress);
                $eventBroadcaster->downloadProgress($lesson);
                $lastProgressBroadcastAt = now();
            });

            $lesson = $this->markAsCompleted($lesson, $downloadedPath);
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
        $settings['download_source'] = $this->sourceUrl;

        $lesson->forceFill(['settings' => $settings])->save();

        return $lesson->fresh('project');
    }

    private function updateProgress(Lesson $lesson, float $progress): Lesson
    {
        $settings = $lesson->settings ?? [];
        $settings['download_progress'] = round($progress, 1);
        $settings['download_status'] = 'running';

        $lesson->forceFill(['settings' => $settings])->save();

        return $lesson->fresh('project');
    }

    private function markAsCompleted(Lesson $lesson, string $path): Lesson
    {
        $settings = $lesson->settings ?? [];
        $settings['downloading'] = false;
        $settings['download_status'] = 'completed';
        $settings['download_progress'] = 100;
        $settings['download_error'] = null;
        $settings['download_source'] = null;

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
