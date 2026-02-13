<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\PipelineVersion;
use App\Services\Lesson\LessonDownloadManager;
use App\Services\Pipeline\PipelineRunService;
use App\Support\LessonTagResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LessonController extends Controller
{
    public function __construct(
        private readonly PipelineRunService $pipelineRunService,
        private readonly LessonDownloadManager $lessonDownloadManager,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Lesson::query()->with([
            'pipelineRuns.pipelineVersion',
            'pipelineRuns.steps',
            'tagRelation',
            'project',
        ]);

        if ($request->filled('tag')) {
            $query->where('tag', $request->input('tag'));
        }

        if ($request->filled('search')) {
            $search = mb_strtolower($request->input('search'));
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        $lessons = $query
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Lesson $lesson) => $this->transformLesson($lesson));

        return response()->json(['data' => $lessons]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'tag' => ['nullable', 'exists:project_tags,slug'],
            'pipeline_version_id' => ['required', 'exists:pipeline_versions,id'],
            'settings' => ['required', 'array'],
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $data['project_id'],
            'name' => $data['name'],
            'tag' => LessonTagResolver::resolve($data['tag'] ?? null),
            'settings' => $data['settings'],
        ]);

        $pipelineVersion = PipelineVersion::query()->findOrFail($data['pipeline_version_id']);
        $this->pipelineRunService->createRun($lesson, $pipelineVersion, dispatchJob: false);

        $lesson->load(['pipelineRuns.pipelineVersion', 'pipelineRuns.steps', 'tagRelation', 'project']);

        return response()->json(['data' => $this->transformLesson($lesson)], 201);
    }

    public function update(Request $request, Lesson $lesson): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'tag' => ['nullable', 'exists:project_tags,slug'],
            'settings' => ['required', 'array'],
            'pipeline_version_id' => ['nullable', 'exists:pipeline_versions,id'],
        ]);

        $updatePayload = [
            'project_id' => $data['project_id'],
            'name' => $data['name'],
            'settings' => $data['settings'],
        ];

        if (array_key_exists('tag', $data)) {
            $updatePayload['tag'] = LessonTagResolver::resolve($data['tag']);
        }

        $lesson->update($updatePayload);

        if (! empty($data['pipeline_version_id'])) {
            $pipelineVersion = PipelineVersion::query()->findOrFail((int) $data['pipeline_version_id']);
            $latestRun = $lesson->latestPipelineRun;

            if ($latestRun === null || $latestRun->pipeline_version_id !== $pipelineVersion->id) {
                $this->pipelineRunService->createRun($lesson, $pipelineVersion);
            }
        }

        return response()->json([
            'data' => $this->transformLesson(
                $lesson->load(['pipelineRuns.pipelineVersion', 'pipelineRuns.steps', 'tagRelation', 'project'])
            ),
        ]);
    }

    public function uploadAudio(Request $request, Lesson $lesson): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimetypes:audio/mpeg', 'max:512000'],
        ]);

        $file = $data['file'];
        $path = 'lessons/'.$lesson->id.'.mp3';

        Storage::disk('local')->putFileAs('lessons', $file, $lesson->id.'.mp3');

        $lesson->update(['source_filename' => $path]);

        $this->pipelineRunService->dispatchQueuedRuns($lesson);

        return response()->json([
            'data' => $this->transformLesson(
                $lesson->load(['pipelineRuns.pipelineVersion', 'pipelineRuns.steps', 'tagRelation', 'project'])
            ),
        ]);
    }

    public function download(Request $request, Lesson $lesson): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url'],
        ]);

        $lesson = $this->lessonDownloadManager->startDownload(
            $lesson->load('project'),
            $data['url']
        );

        return response()->json([
            'data' => $this->transformLesson(
                $lesson->load(['pipelineRuns.pipelineVersion', 'pipelineRuns.steps', 'tagRelation', 'project'])
            ),
        ], 202);
    }

    private function transformLesson(Lesson $lesson): array
    {
        $lesson->loadMissing('pipelineRuns.pipelineVersion', 'pipelineRuns.steps', 'tagRelation', 'project');

        $runs = $lesson->pipelineRuns
            ->sortByDesc(fn ($run) => $run->id)
            ->values();

        $currentRun = $runs->first();

        return [
            'id' => $lesson->id,
            'project' => $lesson->project
                ? [
                    'id' => $lesson->project->id,
                    'name' => $lesson->project->name,
                ]
                : null,
            'name' => $lesson->name,
            'tag' => $lesson->tag,
            'tag_meta' => $lesson->tagRelation
                ? [
                    'slug' => $lesson->tagRelation->slug,
                    'description' => $lesson->tagRelation->description,
                ]
                : null,
            'source_filename' => $lesson->source_filename,
            'pipeline_version' => $currentRun && $currentRun->pipelineVersion
                ? [
                    'id' => $currentRun->pipelineVersion->id,
                    'version' => $currentRun->pipelineVersion->version,
                    'title' => $currentRun->pipelineVersion->title,
                ]
                : null,
            'pipeline_runs' => $runs
                ->map(function ($run) {
                    $steps = $run->relationLoaded('steps') ? $run->steps : collect();
                    $stepsTotal = $steps?->count() ?? 0;
                    $stepsCompleted = $steps?->where('status', 'done')->count() ?? 0;

                    return [
                        'id' => $run->id,
                        'status' => $run->status,
                        'pipeline_version' => $run->pipelineVersion
                            ? [
                                'id' => $run->pipelineVersion->id,
                                'version' => $run->pipelineVersion->version,
                                'title' => $run->pipelineVersion->title,
                            ]
                            : null,
                        'state' => $run->state ?? [],
                        'steps_total' => $stepsTotal,
                        'steps_completed' => $stepsCompleted,
                        'created_at' => optional($run->created_at)->toISOString(),
                        'updated_at' => optional($run->updated_at)->toISOString(),
                    ];
                })
                ->all(),
            'settings' => $lesson->settings,
            'created_at' => optional($lesson->created_at)->toISOString(),
            'updated_at' => optional($lesson->updated_at)->toISOString(),
        ];
    }
}
