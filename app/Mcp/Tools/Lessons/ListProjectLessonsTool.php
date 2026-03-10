<?php

namespace App\Mcp\Tools\Lessons;

use App\Mcp\Support\McpModelResolver;
use App\Mcp\Support\McpPresenter;
use App\Services\Project\ProjectDetailsQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-project-lessons')]
#[Description('Возвращает список уроков проекта вместе с длительностью, статусом загрузки и прогонами уроков.')]
#[IsReadOnly]
#[IsIdempotent]
class ListProjectLessonsTool extends Tool
{
    public function __construct(
        private readonly McpModelResolver $mcpModelResolver,
        private readonly ProjectDetailsQuery $projectDetailsQuery,
        private readonly McpPresenter $mcpPresenter,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'min:1'],
        ], attributes: [
            'project_id' => 'проект',
        ]);

        $viewer = $this->mcpModelResolver->user($request);
        $project = $this->mcpModelResolver->visibleProject($viewer, (int) $validated['project_id']);
        $project = $this->projectDetailsQuery->get($project);

        return Response::structured([
            'project' => $this->mcpPresenter->project($project),
            'lessons' => $project->lessons
                ->map(fn ($lesson): array => $this->mcpPresenter->lesson($lesson))
                ->values()
                ->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->required()
                ->description('ID проекта, для которого нужен список уроков.'),
        ];
    }
}
