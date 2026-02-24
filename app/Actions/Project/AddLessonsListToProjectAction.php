<?php

namespace App\Actions\Project;

use App\Models\Project;
use Illuminate\Validation\ValidationException;

class AddLessonsListToProjectAction
{
    public function __construct(
        private readonly ParseLessonsListAction $parseLessonsListAction,
        private readonly CreateProjectLessonFromYoutubeAction $createProjectLessonFromYoutubeAction,
    ) {}

    public function handle(Project $project, string $lessonsList): void
    {
        $project->refresh();

        if ($project->default_pipeline_version_id === null) {
            throw ValidationException::withMessages([
                'newLessonsList' => 'Для проекта не задана версия шаблона по умолчанию.',
            ]);
        }

        $parsedLessons = $this->parseLessonsListAction->handle($lessonsList, 'newLessonsList');

        foreach ($parsedLessons as $lesson) {
            $this->createProjectLessonFromYoutubeAction->handle(
                project: $project,
                lessonName: $lesson['name'],
                youtubeUrl: $lesson['url'],
                pipelineVersionId: (int) $project->default_pipeline_version_id,
            );
        }
    }
}
