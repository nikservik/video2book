<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Project::query()->with(['pipelineVersion', 'tagRelation']);

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
            'pipeline_version_id' => $data['pipeline_version_id'],
            'settings' => $data['settings'],
        ])->load(['pipelineVersion', 'tagRelation']);

        return response()->json(['data' => $this->transformProject($project)], 201);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tag' => ['required', 'exists:project_tags,slug'],
            'pipeline_version_id' => ['required', 'exists:pipeline_versions,id'],
            'settings' => ['required', 'array'],
        ]);

        $project->update($data);

        return response()->json(['data' => $this->transformProject($project->load(['pipelineVersion', 'tagRelation']))]);
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

        return response()->json(['data' => $this->transformProject($project->load(['pipelineVersion', 'tagRelation']))]);
    }

    private function transformProject(Project $project): array
    {
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
            'pipeline_version' => $project->pipelineVersion
                ? [
                    'id' => $project->pipelineVersion->id,
                    'version' => $project->pipelineVersion->version,
                    'title' => $project->pipelineVersion->title,
                ]
                : null,
            'settings' => $project->settings,
            'created_at' => optional($project->created_at)->toISOString(),
            'updated_at' => optional($project->updated_at)->toISOString(),
        ];
    }
}
