<?php

namespace App\Http\Controllers\Api;

use App\Actions\Pipeline\GetPipelineVersionOptionsAction;
use App\Actions\Project\CreateProjectLessonFromAudioAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProjectLessonRequest;
use App\Mcp\Support\McpPresenter;
use App\Models\Project;
use App\Models\User;
use App\Services\Project\LessonRunsQuery;
use App\Services\Project\ProjectDetailsQuery;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProjectLessonsController extends Controller
{
    public function __construct(
        private readonly CreateProjectLessonFromAudioAction $createProjectLessonFromAudioAction,
        private readonly GetPipelineVersionOptionsAction $getPipelineVersionOptionsAction,
        private readonly LessonRunsQuery $lessonRunsQuery,
        private readonly McpPresenter $mcpPresenter,
        private readonly ProjectDetailsQuery $projectDetailsQuery,
    ) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $project = $this->visibleProject($request, $project);
        $project = $this->projectDetailsQuery->get($project)->loadCount('lessons');

        return response()->json([
            'data' => [
                'project' => $this->mcpPresenter->project($project),
                'pipeline_versions' => $this->pipelineVersionOptions($request),
                'lessons' => $project->lessons
                    ->map(fn ($lesson): array => $this->mcpPresenter->lesson($lesson))
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function store(StoreProjectLessonRequest $request, Project $project): JsonResponse
    {
        $project = $this->visibleProject($request, $project);
        $pipelineVersionId = $this->resolvePipelineVersionId($request, $project);

        $lesson = $this->createProjectLessonFromAudioAction->handle(
            project: $project,
            lessonName: $request->lessonName(),
            audioFile: $request->audioFile(),
            pipelineVersionId: $pipelineVersionId,
        );

        $lesson->setRelation('pipelineRuns', $this->lessonRunsQuery->get($lesson));

        return response()->json([
            'data' => [
                'project' => $this->mcpPresenter->project($project->fresh()->loadCount('lessons')),
                'lesson' => $this->mcpPresenter->lesson($lesson),
            ],
        ], 201);
    }

    private function resolvePipelineVersionId(StoreProjectLessonRequest $request, Project $project): int
    {
        $pipelineVersionId = $request->requestedPipelineVersionId();

        if ($pipelineVersionId !== null) {
            return $pipelineVersionId;
        }

        if ($project->default_pipeline_version_id === null) {
            throw ValidationException::withMessages([
                'pipeline_version_id' => 'Для проекта не задана версия шаблона по умолчанию.',
            ]);
        }

        $pipelineVersionId = (int) $project->default_pipeline_version_id;

        if (! in_array($pipelineVersionId, $this->availablePipelineVersionIds($request), true)) {
            throw ValidationException::withMessages([
                'pipeline_version_id' => 'Версия шаблона по умолчанию недоступна.',
            ]);
        }

        return $pipelineVersionId;
    }

    /**
     * @return array<int, int>
     */
    private function availablePipelineVersionIds(Request $request): array
    {
        return collect($this->pipelineVersionOptions($request))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @return array<int, array{id:int,label:string,description:string|null}>
     */
    private function pipelineVersionOptions(Request $request): array
    {
        $viewer = $request->user();

        return $this->getPipelineVersionOptionsAction->handle($viewer instanceof User ? $viewer : null);
    }

    private function visibleProject(Request $request, Project $project): Project
    {
        $viewer = $request->user();

        if (! $viewer instanceof User) {
            throw new ModelNotFoundException;
        }

        return Project::query()
            ->whereKey($project->id)
            ->whereHas('folder', fn ($query) => $query->visibleTo($viewer))
            ->firstOrFail();
    }
}
