<?php

namespace App\Mcp\Tools\Folders;

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

#[Name('list-project-folders')]
#[Description('Возвращает список доступных пользователю папок проектов с количеством проектов в каждой папке.')]
#[IsReadOnly]
#[IsIdempotent]
class ListProjectFoldersTool extends Tool
{
    public function __construct(
        private readonly McpModelResolver $mcpModelResolver,
        private readonly ProjectFoldersQuery $projectFoldersQuery,
        private readonly McpPresenter $mcpPresenter,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $viewer = $this->mcpModelResolver->user($request);
        $folders = $this->projectFoldersQuery->get($viewer);

        return Response::structured([
            'folders' => $folders
                ->map(fn ($folder): array => $this->mcpPresenter->folder($folder))
                ->values()
                ->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
