<?php

namespace App\Actions\Project;

use App\Actions\Lesson\UpdateLessonAudioDurationAction;
use App\Models\Lesson;
use App\Models\Project;

class RecalculateProjectLessonsAudioDurationAction
{
    public const PROJECT_TOTAL_DURATION_SETTING_KEY = 'lessons_audio_duration_seconds';

    public function __construct(
        private readonly UpdateLessonAudioDurationAction $updateLessonAudioDurationAction,
    ) {}

    public function handle(Project $project): int
    {
        $totalDurationSeconds = 0;

        Lesson::query()
            ->where('project_id', $project->id)
            ->select(['id', 'project_id', 'source_filename', 'settings'])
            ->each(function (Lesson $lesson) use (&$totalDurationSeconds): void {
                if (blank($lesson->source_filename)) {
                    return;
                }

                $durationSeconds = data_get(
                    $lesson->settings,
                    UpdateLessonAudioDurationAction::LESSON_DURATION_SETTING_KEY
                );

                if (! is_numeric($durationSeconds) || (int) $durationSeconds <= 0) {
                    $durationSeconds = $this->updateLessonAudioDurationAction->handle($lesson);
                }

                if (is_numeric($durationSeconds) && (int) $durationSeconds > 0) {
                    $totalDurationSeconds += (int) $durationSeconds;
                }
            });

        $settings = $project->settings ?? [];
        $settings[self::PROJECT_TOTAL_DURATION_SETTING_KEY] = $totalDurationSeconds;

        $project->forceFill(['settings' => $settings])->save();

        return $totalDurationSeconds;
    }
}
