<?php

namespace App\Mcp\Tools\Projects;

use App\Actions\Project\UpdateProjectNameAction;
use App\Mcp\Support\McpModelResolver;
use App\Mcp\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update-project')]
#[Description('Обновляет название проекта, referer и версию шаблона по умолчанию.')]
class UpdateProjectTool extends Tool
{
    public function __construct(
        private readonly McpModelResolver $mcpModelResolver,
        private readonly UpdateProjectNameAction $updateProjectNameAction,
        private readonly McpPresenter $mcpPresenter,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'referer' => ['nullable', 'url', 'starts_with:https://'],
            'default_pipeline_version_id' => ['nullable', 'integer'],
        ]);

        $viewer = $this->mcpModelResolver->user($request);
        $project = $this->mcpModelResolver->visibleProject($viewer, (int) $validated['project_id']);
        $defaultPipelineVersionId = $this->mcpModelResolver->allowedPipelineVersionId(
            $viewer,
            isset($validated['default_pipeline_version_id']) ? (int) $validated['default_pipeline_version_id'] : null,
        );

        $this->updateProjectNameAction->handle(
            project: $project,
            name: trim($validated['name']),
            referer: $validated['referer'] ?? null,
            defaultPipelineVersionId: $defaultPipelineVersionId,
        );

        $project = $project->fresh()->loadCount('lessons');

        return Response::structured([
            'project' => $this->mcpPresenter->project($project),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->required()
                ->description('ID проекта для обновления.'),
            'name' => $schema->string()
                ->required()
                ->description('Новое название проекта.')
                ->max(255),
            'referer' => $schema->string()
                ->description('HTTPS referer проекта.')
                ->nullable()
                ->format('uri'),
            'default_pipeline_version_id' => $schema->integer()
                ->description('Новая версия шаблона по умолчанию.')
                ->nullable(),
        ];
    }
}
