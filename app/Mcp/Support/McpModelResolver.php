<?php

namespace App\Mcp\Support;

use App\Actions\Pipeline\GetPipelineVersionOptionsAction;
use App\Models\Folder;
use App\Models\Lesson;
use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\Project;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

class McpModelResolver
{
    public function __construct(
        private readonly GetPipelineVersionOptionsAction $getPipelineVersionOptionsAction,
    ) {}

    public function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'token' => 'Пользователь не авторизован.',
            ]);
        }

        return $user;
    }

    public function visibleFolder(User $viewer, int $folderId, string $field = 'folder_id'): Folder
    {
        $folder = Folder::query()
            ->visibleTo($viewer)
            ->find($folderId);

        if (! $folder instanceof Folder) {
            throw ValidationException::withMessages([
                $field => 'Папка не найдена или недоступна.',
            ]);
        }

        return $folder;
    }

    public function visibleProject(User $viewer, int $projectId, string $field = 'project_id'): Project
    {
        $project = Project::query()
            ->whereKey($projectId)
            ->whereHas('folder', fn ($query) => $query->visibleTo($viewer))
            ->first();

        if (! $project instanceof Project) {
            throw ValidationException::withMessages([
                $field => 'Проект не найден или недоступен.',
            ]);
        }

        return $project;
    }

    public function visibleLesson(User $viewer, int $lessonId, string $field = 'lesson_id'): Lesson
    {
        $lesson = Lesson::query()
            ->whereKey($lessonId)
            ->whereHas('project.folder', fn ($query) => $query->visibleTo($viewer))
            ->first();

        if (! $lesson instanceof Lesson) {
            throw ValidationException::withMessages([
                $field => 'Урок не найден или недоступен.',
            ]);
        }

        return $lesson;
    }

    public function visibleRun(User $viewer, int $runId, string $field = 'run_id'): PipelineRun
    {
        $run = PipelineRun::query()
            ->whereKey($runId)
            ->whereHas('lesson.project.folder', fn ($query) => $query->visibleTo($viewer))
            ->first();

        if (! $run instanceof PipelineRun) {
            throw ValidationException::withMessages([
                $field => 'Прогон не найден или недоступен.',
            ]);
        }

        return $run;
    }

    public function stepForRun(PipelineRun $run, int $stepId, string $field = 'step_id'): PipelineRunStep
    {
        $step = $run->steps()
            ->with('stepVersion:id,name,type,settings')
            ->find($stepId);

        if (! $step instanceof PipelineRunStep) {
            throw ValidationException::withMessages([
                $field => 'Шаг не найден для выбранного прогона.',
            ]);
        }

        return $step;
    }

    public function allowedPipelineVersionId(User $viewer, ?int $pipelineVersionId, string $field = 'pipeline_version_id'): ?int
    {
        if ($pipelineVersionId === null) {
            return null;
        }

        $allowedIds = collect($this->getPipelineVersionOptionsAction->handle($viewer))
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if (! in_array($pipelineVersionId, $allowedIds, true)) {
            throw ValidationException::withMessages([
                $field => 'Версия шаблона недоступна.',
            ]);
        }

        return $pipelineVersionId;
    }
}
