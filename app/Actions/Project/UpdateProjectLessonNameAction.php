<?php

namespace App\Actions\Project;

use App\Models\Lesson;
use App\Models\Project;

class UpdateProjectLessonNameAction
{
    public function handle(Project $project, int $lessonId, string $name): void
    {
        $lesson = Lesson::query()
            ->where('project_id', $project->id)
            ->findOrFail($lessonId);

        $lesson->update([
            'name' => $name,
        ]);
    }
}
