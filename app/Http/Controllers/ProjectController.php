<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
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
