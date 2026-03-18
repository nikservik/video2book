<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mcp\Support\McpPresenter;
use App\Models\Folder;
use App\Models\User;
use App\Services\Project\ProjectFoldersQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FoldersController extends Controller
{
    public function index(
        Request $request,
        ProjectFoldersQuery $projectFoldersQuery,
        McpPresenter $mcpPresenter,
    ): JsonResponse {
        $viewer = $request->user();

        $folders = $projectFoldersQuery
            ->get($viewer instanceof User ? $viewer : null)
            ->map(function (Folder $folder) use ($mcpPresenter): array {
                return [
                    ...$mcpPresenter->folder($folder, includeVisibility: false),
                    'projects' => $folder->projects
                        ->map(fn ($project): array => $mcpPresenter->project($project))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return response()->json(['data' => $folders]);
    }
}
