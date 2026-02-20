<?php

namespace App\Services\Home;

use App\Jobs\DownloadLessonAudioJob;
use App\Jobs\NormalizeUploadedLessonAudioJob;
use App\Jobs\ProcessPipelineJob;
use App\Models\Lesson;
use App\Models\PipelineRun;
use Illuminate\Support\Facades\DB;

class QueueWidgetDataProvider
{
    /**
     * @return array{
     *   title: string,
     *   items: array<int, array{
     *      type: 'pipeline'|'download',
     *      task_key: string,
     *      lesson_name: string,
     *      pipeline_label: string,
     *      icon_color_class: string,
     *      steps_progress: string,
     *      steps?: array<int, array{name: string, status_label: string, status_badge_class: string}>,
     *      progress?: float,
     *      progress_label?: string,
     *      progress_width?: string
     *   }>
     * }
     */
    public function get(): array
    {
        $jobs = DB::table('jobs')
            ->select(['id', 'queue', 'payload', 'reserved_at', 'available_at', 'created_at'])
            ->orderBy('available_at')
            ->orderBy('id')
            ->get();

        $pipelineTasks = [];
        $downloadTasks = [];

        foreach ($jobs as $job) {
            $payload = json_decode((string) $job->payload, true);

            if (! is_array($payload)) {
                continue;
            }

            $commandName = (string) (data_get($payload, 'data.commandName') ?? data_get($payload, 'displayName') ?? '');

            if ($commandName === ProcessPipelineJob::class) {
                $pipelineRunId = $this->extractIntegerPropertyFromPayload($payload, 'pipelineRunId');

                if ($pipelineRunId !== null) {
                    $pipelineTasks[] = [
                        'id' => (int) $job->id,
                        'pipeline_run_id' => $pipelineRunId,
                        'is_running' => $job->reserved_at !== null,
                    ];
                }

                continue;
            }

            if (in_array($commandName, [DownloadLessonAudioJob::class, NormalizeUploadedLessonAudioJob::class], true)) {
                $lessonId = $this->extractIntegerPropertyFromPayload($payload, 'lessonId');

                if ($lessonId !== null) {
                    $downloadTasks[] = [
                        'id' => (int) $job->id,
                        'lesson_id' => $lessonId,
                        'is_running' => $job->reserved_at !== null,
                    ];
                }
            }
        }

        $pipelineRunIds = collect($pipelineTasks)->pluck('pipeline_run_id')->unique()->values()->all();
        $lessonIds = collect($downloadTasks)->pluck('lesson_id')->unique()->values()->all();

        $pipelineRuns = PipelineRun::query()
            ->whereIn('id', $pipelineRunIds)
            ->with([
                'lesson:id,name',
                'pipelineVersion:id,title,version',
                'steps' => fn ($query) => $query
                    ->with(['stepVersion:id,name'])
                    ->orderBy('position')
                    ->select(['id', 'pipeline_run_id', 'step_version_id', 'position', 'status']),
            ])
            ->get()
            ->keyBy('id');

        $lessons = Lesson::query()
            ->whereIn('id', $lessonIds)
            ->with([
                'pipelineRuns' => fn ($query) => $query
                    ->with([
                        'pipelineVersion:id,title,version',
                        'steps:id,pipeline_run_id,status',
                    ])
                    ->orderByDesc('id')
                    ->select(['id', 'lesson_id', 'pipeline_version_id', 'status']),
            ])
            ->select(['id', 'name', 'settings'])
            ->get()
            ->keyBy('id');

        $items = [];

        foreach ($pipelineTasks as $task) {
            /** @var PipelineRun|null $pipelineRun */
            $pipelineRun = $pipelineRuns->get($task['pipeline_run_id']);

            if ($pipelineRun === null) {
                continue;
            }

            $stepsDone = $pipelineRun->steps->where('status', 'done')->count();
            $stepsTotal = $pipelineRun->steps->count();

            $items[] = [
                'type' => 'pipeline',
                'task_key' => 'pipeline:'.$task['id'],
                'lesson_name' => $pipelineRun->lesson?->name ?? 'Урок не найден',
                'pipeline_label' => sprintf(
                    '%s • v%s',
                    $pipelineRun->pipelineVersion?->title ?? 'Без названия',
                    (string) ($pipelineRun->pipelineVersion?->version ?? '—')
                ),
                'icon_color_class' => $this->taskIconColorClass((bool) $task['is_running']),
                'steps_progress' => $stepsDone.'/'.$stepsTotal,
                'steps' => $pipelineRun->steps->map(fn ($step): array => [
                    'name' => $step->stepVersion?->name ?? 'Без названия шага',
                    'status_label' => $this->stepStatusLabel($step->status),
                    'status_badge_class' => $this->stepStatusBadgeClass($step->status),
                ])->values()->all(),
            ];
        }

        foreach ($downloadTasks as $task) {
            /** @var Lesson|null $lesson */
            $lesson = $lessons->get($task['lesson_id']);

            if ($lesson === null) {
                continue;
            }

            $pipelineRun = $lesson->pipelineRuns
                ->first(fn ($run): bool => in_array($run->status, ['queued', 'running', 'paused'], true))
                ?? $lesson->pipelineRuns->first();

            $progress = (float) data_get($lesson->settings, 'download_progress', 0);
            $progress = max(0, min(100, $progress));
            $stepsDone = $pipelineRun?->steps?->where('status', 'done')->count() ?? 0;
            $stepsTotal = $pipelineRun?->steps?->count() ?? 0;

            $items[] = [
                'type' => 'download',
                'task_key' => 'download:'.$task['id'],
                'lesson_name' => $lesson->name,
                'pipeline_label' => sprintf(
                    '%s • v%s',
                    $pipelineRun?->pipelineVersion?->title ?? 'Без названия',
                    (string) ($pipelineRun?->pipelineVersion?->version ?? '—')
                ),
                'icon_color_class' => $this->taskIconColorClass((bool) $task['is_running']),
                'steps_progress' => $stepsDone.'/'.$stepsTotal,
                'progress' => $progress,
                'progress_label' => $this->formatProgress($progress),
                'progress_width' => $this->formatProgressWidth($progress),
            ];
        }

        return [
            'title' => 'Очередь обработки',
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractIntegerPropertyFromPayload(array $payload, string $property): ?int
    {
        $command = (string) data_get($payload, 'data.command', '');

        if ($command === '') {
            return null;
        }

        if (preg_match('/"'.preg_quote($property, '/').'";i:(\d+)/', $command, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/"'.preg_quote($property, '/').'";s:\d+:"(\d+)"/', $command, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function stepStatusLabel(?string $status): string
    {
        return match ($status) {
            'done' => 'Готово',
            'pending' => 'В очереди',
            'running' => 'Обработка',
            'paused' => 'На паузе',
            'failed' => 'Ошибка',
            default => 'Неизвестно',
        };
    }

    private function stepStatusBadgeClass(?string $status): string
    {
        return match ($status) {
            'done' => 'inline-flex items-center whitespace-nowrap rounded-full bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-400/10 dark:text-green-400',
            'pending' => 'inline-flex items-center whitespace-nowrap rounded-full bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300',
            'running' => 'inline-flex items-center whitespace-nowrap rounded-full bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-400/10 dark:text-yellow-300',
            'paused' => 'inline-flex items-center whitespace-nowrap rounded-full bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-400/10 dark:text-blue-400',
            'failed' => 'inline-flex items-center whitespace-nowrap rounded-full bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-400/10 dark:text-red-400',
            default => 'inline-flex items-center whitespace-nowrap rounded-full bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300',
        };
    }

    private function formatProgress(float $progress): string
    {
        return rtrim(rtrim(number_format($progress, 1, '.', ''), '0'), '.').'%';
    }

    private function formatProgressWidth(float $progress): string
    {
        return (string) max(0, min(100, $progress)).'%';
    }

    private function taskIconColorClass(bool $isRunning): string
    {
        return $isRunning
            ? 'text-indigo-600 dark:text-indigo-400'
            : 'text-gray-500 dark:text-gray-400';
    }
}
