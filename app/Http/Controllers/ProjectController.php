<?php

namespace App\Http\Controllers;

use App\Models\PipelineRun;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Project::query()->with([
            'pipelineRuns.pipelineVersion',
            'tagRelation',
        ]);

        if ($request->filled('tag')) {
            $query->where('tag', $request->input('tag'));
        }

        if ($request->filled('search')) {
            $search = mb_strtolower($request->input('search'));
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']);
        }

        $projects = $query->latest()->get()->map(fn (Project $project) => $this->transformProject($project));

        return response()->json(['data' => $projects]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tag' => ['required', 'exists:project_tags,slug'],
            'pipeline_version_id' => ['required', 'exists:pipeline_versions,id'],
            'settings' => ['required', 'array'],
        ]);

        $project = Project::query()->create([
            'name' => $data['name'],
            'tag' => $data['tag'],
            'settings' => $data['settings'],
        ]);

        $this->createPipelineRun($project, $data['pipeline_version_id']);
        $project->load(['pipelineRuns.pipelineVersion', 'tagRelation']);

        return response()->json(['data' => $this->transformProject($project)], 201);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tag' => ['required', 'exists:project_tags,slug'],
            'settings' => ['required', 'array'],
            'pipeline_version_id' => ['nullable', 'exists:pipeline_versions,id'],
        ]);

        $project->update([
            'name' => $data['name'],
            'tag' => $data['tag'],
            'settings' => $data['settings'],
        ]);

        if (! empty($data['pipeline_version_id'])) {
            $this->createPipelineRun($project, $data['pipeline_version_id']);
        }

        return response()->json([
            'data' => $this->transformProject(
                $project->load(['pipelineRuns.pipelineVersion', 'tagRelation'])
            ),
        ]);
    }

    public function uploadAudio(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimetypes:audio/mpeg', 'max:512000'],
        ]);

        $file = $data['file'];
        $path = 'projects/'.$project->id.'.mp3';

        Storage::disk('local')->putFileAs('projects', $file, $project->id.'.mp3');

        $project->update(['source_filename' => $path]);

        return response()->json([
            'data' => $this->transformProject(
                $project->load(['pipelineRuns.pipelineVersion', 'tagRelation'])
            ),
        ]);
    }

    private function transformProject(Project $project): array
    {
        $project->loadMissing('pipelineRuns.pipelineVersion', 'tagRelation');

        $runs = $project->pipelineRuns
            ->sortByDesc(fn (PipelineRun $run) => $run->id)
            ->values();

        /** @var PipelineRun|null $currentRun */
        $currentRun = $runs->first();

        return [
            'id' => $project->id,
            'name' => $project->name,
            'tag' => $project->tag,
            'tag_meta' => $project->tagRelation
                ? [
                    'slug' => $project->tagRelation->slug,
                    'description' => $project->tagRelation->description,
                ]
                : null,
            'source_filename' => $project->source_filename,
            'pipeline_version' => $currentRun && $currentRun->pipelineVersion
                ? [
                    'id' => $currentRun->pipelineVersion->id,
                    'version' => $currentRun->pipelineVersion->version,
                    'title' => $currentRun->pipelineVersion->title,
                ]
                : null,
            'pipeline_runs' => $runs
                ->map(fn (PipelineRun $run) => [
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
                    'created_at' => optional($run->created_at)->toISOString(),
                    'updated_at' => optional($run->updated_at)->toISOString(),
                ])
                ->all(),
            'settings' => $project->settings,
            'created_at' => optional($project->created_at)->toISOString(),
            'updated_at' => optional($project->updated_at)->toISOString(),
        ];
    }

    private function createPipelineRun(Project $project, int $pipelineVersionId): PipelineRun
    {
        $latestRun = $project->latestPipelineRun;

        if ($latestRun && $latestRun->pipeline_version_id === $pipelineVersionId) {
            return $latestRun;
        }

        return $project->pipelineRuns()->create([
            'pipeline_version_id' => $pipelineVersionId,
            'status' => 'queued',
            'state' => [],
        ]);
    }
}
