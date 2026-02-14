<?php

namespace App\Actions\Project;

use App\Models\Lesson;
use App\Models\Project;

class DeleteProjectLessonAction
{
    public function handle(Project $project, int $lessonId): void
    {
        $lesson = Lesson::query()
            ->where('project_id', $project->id)
            ->findOrFail($lessonId);

        $lesson->delete();
    }
}
