<?php

namespace App\Actions\Project;

use App\Models\Project;
use Illuminate\Validation\ValidationException;

class CreateProjectFromLessonsListAction
{
    public function __construct(
        private readonly ParseLessonsListAction $parseLessonsListAction,
        private readonly CreateProjectLessonFromYoutubeAction $createProjectLessonFromYoutubeAction,
    ) {}

    public function handle(
        string $projectName,
        ?string $referer,
        ?int $defaultPipelineVersionId,
        ?string $lessonsList,
    ): Project {
        $parsedLessons = $this->parseLessonsListAction->handle($lessonsList, 'newProjectLessonsList');

        if ($parsedLessons !== [] && $defaultPipelineVersionId === null) {
            throw ValidationException::withMessages([
                'newProjectDefaultPipelineVersionId' => 'Выберите версию шаблона по умолчанию для создания уроков.',
            ]);
        }

        $project = Project::query()->create([
            'name' => trim($projectName),
            'tags' => null,
            'default_pipeline_version_id' => $defaultPipelineVersionId,
            'referer' => $referer,
        ]);

        foreach ($parsedLessons as $lesson) {
            $this->createProjectLessonFromYoutubeAction->handle(
                project: $project,
                lessonName: $lesson['name'],
                youtubeUrl: $lesson['url'],
                pipelineVersionId: (int) $defaultPipelineVersionId,
            );
        }

        return $project->fresh();
    }
}
