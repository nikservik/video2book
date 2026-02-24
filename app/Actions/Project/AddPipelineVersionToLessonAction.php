<?php

namespace App\Actions\Project;

use App\Models\Lesson;
use App\Models\PipelineVersion;
use App\Models\Project;
use App\Services\Pipeline\PipelineRunService;
use Illuminate\Validation\ValidationException;

class AddPipelineVersionToLessonAction
{
    public function __construct(
        private readonly PipelineRunService $pipelineRunService,
    ) {}

    public function handle(Project $project, int $lessonId, int $pipelineVersionId): void
    {
        $lesson = Lesson::query()
            ->where('project_id', $project->id)
            ->findOrFail($lessonId);

        $alreadyAdded = $lesson->pipelineRuns()
            ->where('pipeline_version_id', $pipelineVersionId)
            ->exists();

        if ($alreadyAdded) {
            throw ValidationException::withMessages([
                'pipeline_version_id' => 'Эта версия шаблона уже добавлена в урок.',
            ]);
        }

        $pipelineVersion = PipelineVersion::query()->findOrFail($pipelineVersionId);

        $this->pipelineRunService->createRunReusingUnchangedPrefix($lesson, $pipelineVersion);
    }
}
