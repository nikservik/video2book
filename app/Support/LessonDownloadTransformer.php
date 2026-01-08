<?php

namespace App\Support;

use App\Models\Lesson;

final class LessonDownloadTransformer
{
    public static function task(Lesson $lesson): array
    {
        $lesson->loadMissing('project');
        $settings = $lesson->settings ?? [];

        return [
            'lesson_id' => $lesson->id,
            'lesson' => [
                'id' => $lesson->id,
                'name' => $lesson->name,
                'project' => $lesson->project
                    ? [
                        'id' => $lesson->project->id,
                        'name' => $lesson->project->name,
                    ]
                    : null,
            ],
            'status' => $settings['download_status'] ?? 'queued',
            'progress' => (float) ($settings['download_progress'] ?? 0),
            'source_url' => $settings['download_source'] ?? null,
            'error' => $settings['download_error'] ?? null,
            'updated_at' => optional($lesson->updated_at)->toISOString(),
        ];
    }
}
