<?php

namespace App\Mcp\Support;

use App\Actions\Project\RecalculateProjectLessonsAudioDurationAction;
use App\Models\Folder;
use App\Models\Lesson;
use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\Project;
use App\Services\Home\QueueWidgetDataProvider;
use App\Support\AudioDurationLabelFormatter;

class McpPresenter
{
    public function __construct(
        private readonly AudioDurationLabelFormatter $audioDurationLabelFormatter,
        private readonly QueueWidgetDataProvider $queueWidgetDataProvider,
    ) {}

    public function folder(Folder $folder): array
    {
        return [
            'id' => $folder->id,
            'name' => $folder->name,
            'hidden' => (bool) $folder->hidden,
            'projects_count' => (int) ($folder->projects_count ?? $folder->projects()->count()),
            'visible_for_user_ids' => collect($folder->visible_for ?? [])
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()
                ->all(),
        ];
    }

    public function project(Project $project): array
    {
        $durationSeconds = data_get(
            $project->settings,
            RecalculateProjectLessonsAudioDurationAction::PROJECT_TOTAL_DURATION_SETTING_KEY
        );

        return [
            'id' => $project->id,
            'folder_id' => (int) $project->folder_id,
            'name' => $project->name,
            'lessons_count' => (int) ($project->lessons_count ?? $project->lessons()->count()),
            'duration_seconds' => is_numeric($durationSeconds) ? (int) $durationSeconds : null,
            'duration_label' => $this->audioDurationLabelFormatter->format($durationSeconds),
            'default_pipeline_version_id' => $project->default_pipeline_version_id,
            'referer' => $project->referer,
            'updated_at' => optional($project->updated_at)->toISOString(),
        ];
    }

    public function lesson(Lesson $lesson): array
    {
        $durationSeconds = data_get($lesson->settings, 'audio_duration_seconds');

        return [
            'id' => $lesson->id,
            'project_id' => (int) $lesson->project_id,
            'name' => $lesson->name,
            'source_filename' => $lesson->source_filename,
            'audio_duration_seconds' => is_numeric($durationSeconds) ? (int) $durationSeconds : null,
            'audio_duration_label' => $this->audioDurationLabelFormatter->format($durationSeconds),
            'download_status' => $this->lessonDownloadStatus($lesson),
            'runs' => $lesson->relationLoaded('pipelineRuns')
                ? $lesson->pipelineRuns->map(fn (PipelineRun $run): array => $this->run($run))->values()->all()
                : [],
        ];
    }

    public function run(PipelineRun $run): array
    {
        $steps = $run->relationLoaded('steps') ? $run->steps : collect();
        $pipelineVersion = $run->pipelineVersion;

        return [
            'id' => $run->id,
            'lesson_id' => (int) $run->lesson_id,
            'status' => $run->status,
            'pipeline_version_id' => $run->pipeline_version_id,
            'pipeline_title' => $pipelineVersion?->title,
            'pipeline_version' => $pipelineVersion?->version,
            'pipeline_label' => $pipelineVersion === null
                ? null
                : sprintf(
                    '%s • v%s',
                    $pipelineVersion->title ?? 'Без названия',
                    (string) $pipelineVersion->version
                ),
            'steps_count' => $steps->count(),
            'done_steps_count' => $steps->where('status', 'done')->count(),
            'created_at' => optional($run->created_at)->toISOString(),
        ];
    }

    public function step(PipelineRunStep $step, bool $includeResult = false): array
    {
        $payload = [
            'id' => $step->id,
            'pipeline_run_id' => (int) $step->pipeline_run_id,
            'step_version_id' => $step->step_version_id,
            'position' => (int) $step->position,
            'name' => $step->stepVersion?->name,
            'type' => $step->stepVersion?->type,
            'status' => $step->status,
            'input_tokens' => $step->input_tokens,
            'output_tokens' => $step->output_tokens,
            'cost' => $step->cost === null ? null : (float) $step->cost,
        ];

        if ($includeResult) {
            $payload['result'] = $step->result;
            $payload['error'] = $step->error;
        }

        return $payload;
    }

    public function queue(): array
    {
        $queue = $this->queueWidgetDataProvider->get();

        return [
            'title' => (string) ($queue['title'] ?? 'Очередь обработки'),
            'items' => collect($queue['items'] ?? [])
                ->map(function (array $item): array {
                    $iconColorClass = (string) ($item['icon_color_class'] ?? '');

                    return [
                        'type' => (string) ($item['type'] ?? 'pipeline'),
                        'task_key' => (string) ($item['task_key'] ?? ''),
                        'status' => str_contains($iconColorClass, 'indigo') ? 'running' : 'queued',
                        'lesson_name' => (string) ($item['lesson_name'] ?? 'Урок не найден'),
                        'pipeline_label' => (string) ($item['pipeline_label'] ?? 'Без шаблона'),
                        'steps_progress' => (string) ($item['steps_progress'] ?? '0/0'),
                        'steps' => $item['steps'] ?? [],
                        'progress' => $item['progress'] ?? null,
                        'progress_label' => $item['progress_label'] ?? null,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    private function lessonDownloadStatus(Lesson $lesson): string
    {
        if (! blank($lesson->source_filename)) {
            return 'loaded';
        }

        $downloadStatus = data_get($lesson->settings, 'download_status');
        $downloadError = data_get($lesson->settings, 'download_error');
        $downloading = (bool) data_get($lesson->settings, 'downloading', false);

        if ($downloadStatus === 'failed' || ! blank($downloadError)) {
            return 'failed';
        }

        if ($downloadStatus === 'completed') {
            return 'loaded';
        }

        if ($downloadStatus === 'running' || $downloading) {
            return 'running';
        }

        return 'queued';
    }
}
