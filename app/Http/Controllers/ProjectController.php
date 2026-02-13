<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\PipelineVersion;
use App\Models\Project;
use App\Services\Lesson\LessonDownloadManager;
use App\Services\Pipeline\PipelineRunService;
use App\Support\LessonTagResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function __construct(
        private readonly PipelineRunService $pipelineRunService,
        private readonly LessonDownloadManager $lessonDownloadManager,
    ) {}

    public function index(): JsonResponse
    {
        $projects = Project::query()
            ->withCount('lessons')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Project $project) => $this->transformProject($project));

        return response()->json(['data' => $projects]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:255'],
        ]);

        $project = Project::query()->create($data);

        return response()->json(['data' => $this->transformProject($project)], 201);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:255'],
        ]);

        $project->update($data);

        return response()->json(['data' => $this->transformProject($project->refresh())]);
    }

    public function storeYoutube(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:255'],
            'pipeline_version_id' => ['required', 'exists:pipeline_versions,id'],
            'lessons' => ['required', 'array', 'min:1'],
            'lessons.*.name' => ['required', 'string', 'max:255'],
            'lessons.*.url' => ['required', 'string', 'starts_with:https://'],
        ]);

        $pipelineVersion = PipelineVersion::query()->findOrFail($data['pipeline_version_id']);

        $project = null;
        $lessonDescriptors = [];

        DB::transaction(function () use (&$project, &$lessonDescriptors, $data, $pipelineVersion): void {
            $project = Project::query()->create([
                'name' => $data['name'],
                'tags' => $data['tags'] ?? null,
            ]);

            foreach ($data['lessons'] as $lessonData) {
                $lesson = Lesson::query()->create([
                    'project_id' => $project->id,
                    'name' => $lessonData['name'],
                    'tag' => LessonTagResolver::resolve(null),
                    'settings' => ['quality' => 'high'],
                ]);

                $this->pipelineRunService->createRun($lesson, $pipelineVersion, dispatchJob: false);

                $lessonDescriptors[] = [
                    'lesson' => $lesson,
                    'url' => $lessonData['url'],
                ];
            }
        });

        foreach ($lessonDescriptors as $descriptor) {
            /** @var Lesson $lesson */
            $lesson = $descriptor['lesson']->fresh('project');
            $this->lessonDownloadManager->startDownload($lesson, $descriptor['url']);
        }

        if ($project === null) {
            abort(500, 'Не удалось создать проект из ссылок.');
        }

        $project = $project->fresh()->loadCount('lessons');

        return response()->json([
            'data' => [
                'project' => $this->transformProject($project),
                'lessons' => array_map(fn ($descriptor) => [
                    'id' => $descriptor['lesson']->id,
                    'name' => $descriptor['lesson']->name,
                ], $lessonDescriptors),
            ],
        ], 201);
    }

    private function transformProject(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'tags' => $project->tags,
            'lessons_count' => $project->lessons_count ?? $project->lessons()->count(),
            'created_at' => optional($project->created_at)->toISOString(),
            'updated_at' => optional($project->updated_at)->toISOString(),
        ];
    }
}
