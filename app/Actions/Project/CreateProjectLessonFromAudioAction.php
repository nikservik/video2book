<?php

namespace App\Actions\Project;

use App\Models\Lesson;
use App\Models\PipelineVersion;
use App\Models\Project;
use App\Services\Lesson\LessonDownloadManager;
use App\Services\Pipeline\PipelineRunService;
use App\Support\LessonTagResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateProjectLessonFromAudioAction
{
    public function __construct(
        private readonly PipelineRunService $pipelineRunService,
        private readonly LessonDownloadManager $lessonDownloadManager,
    ) {}

    public function handle(
        Project $project,
        string $lessonName,
        UploadedFile $audioFile,
        int $pipelineVersionId,
    ): Lesson {
        $pipelineVersion = PipelineVersion::query()->findOrFail($pipelineVersionId);

        $lesson = DB::transaction(function () use ($project, $lessonName, $pipelineVersion): Lesson {
            $lesson = Lesson::query()->create([
                'project_id' => $project->id,
                'name' => trim($lessonName),
                'tag' => LessonTagResolver::resolve(null),
                'settings' => ['quality' => 'low'],
            ]);

            $this->pipelineRunService->createRun($lesson, $pipelineVersion, dispatchJob: false);

            return $lesson;
        });

        return $this->lessonDownloadManager->startUploadedAudioNormalization(
            $lesson->fresh('project'),
            $audioFile,
        );
    }
}
