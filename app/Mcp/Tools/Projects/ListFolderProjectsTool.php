<?php

namespace App\Mcp\Tools\Projects;

use App\Mcp\Support\McpModelResolver;
use App\Mcp\Support\McpPresenter;
use App\Services\Project\ProjectFoldersQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-folder-projects')]
#[Description('Возвращает список проектов в выбранной папке с количеством уроков и длительностью.')]
#[IsReadOnly]
#[IsIdempotent]
class ListFolderProjectsTool extends Tool
{
    public function __construct(
        private readonly McpModelResolver $mcpModelResolver,
        private readonly ProjectFoldersQuery $projectFoldersQuery,
        private readonly McpPresenter $mcpPresenter,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'folder_id' => ['required', 'integer'],
        ]);

        $viewer = $this->mcpModelResolver->user($request);
        $folderId = (int) $validated['folder_id'];
        $this->mcpModelResolver->visibleFolder($viewer, $folderId);

        $folder = $this->projectFoldersQuery->get($viewer)->firstWhere('id', $folderId);

        if ($folder === null) {
            $folder = $this->mcpModelResolver->visibleFolder($viewer, $folderId)->load([
                'projects' => fn ($query) => $query
                    ->withCount('lessons')
                    ->orderByDesc('updated_at'),
            ]);
        }

        return Response::structured([
            'folder' => $this->mcpPresenter->folder($folder),
            'projects' => $folder->projects
                ->map(fn ($project): array => $this->mcpPresenter->project($project))
                ->values()
                ->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'folder_id' => $schema->integer()
                ->required()
                ->description('ID папки, для которой нужен список проектов.'),
        ];
    }
}
