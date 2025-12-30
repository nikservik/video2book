<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\ProjectTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectTagController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ProjectTag::query()->orderBy('slug')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'alpha_dash', 'max:50', 'unique:project_tags,slug'],
            'description' => ['nullable', 'string'],
        ]);

        $tag = ProjectTag::query()->create($data);

        return response()->json(['data' => $tag], 201);
    }

    public function update(Request $request, ProjectTag $projectTag): JsonResponse
    {
        $data = $request->validate([
            'slug' => [
                'sometimes',
                'alpha_dash',
                'max:50',
                Rule::unique('project_tags', 'slug')->ignore($projectTag->id),
            ],
            'description' => ['nullable', 'string'],
        ]);

        $projectTag->update($data);

        return response()->json(['data' => $projectTag]);
    }

    public function destroy(ProjectTag $projectTag): JsonResponse
    {
        abort_if(
            Lesson::query()->where('tag', $projectTag->slug)->exists(),
            422,
            'Cannot delete a tag with active lessons.'
        );

        $projectTag->delete();

        return response()->json([], 204);
    }
}
