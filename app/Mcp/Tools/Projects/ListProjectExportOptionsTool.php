<?php

namespace App\Mcp\Tools\Projects;

use App\Actions\Project\GetProjectExportPipelineStepOptionsAction;
use App\Mcp\Support\McpModelResolver;
use App\Mcp\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-project-export-options')]
#[Description('Возвращает доступные варианты экспорта проекта: версии шаблонов и текстовые шаги.')]
#[IsReadOnly]
#[IsIdempotent]
class ListProjectExportOptionsTool extends Tool
{
    public function __construct(
        private readonly McpModelResolver $mcpModelResolver,
        private readonly GetProjectExportPipelineStepOptionsAction $getProjectExportPipelineStepOptionsAction,
        private readonly McpPresenter $mcpPresenter,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
        ]);

        $viewer = $this->mcpModelResolver->user($request);
        $project = $this->mcpModelResolver->visibleProject($viewer, (int) $validated['project_id']);

        return Response::structured([
            'project' => $this->mcpPresenter->project($project),
            'pipeline_versions' => $this->getProjectExportPipelineStepOptionsAction->handle($project),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->required()
                ->description('ID проекта, для которого нужно показать опции экспорта.'),
        ];
    }
}
