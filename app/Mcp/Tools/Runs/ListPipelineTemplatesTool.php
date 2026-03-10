<?php

namespace App\Mcp\Tools\Runs;

use App\Actions\Pipeline\GetPipelineTemplatesCatalogAction;
use App\Mcp\Support\McpModelResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-pipeline-templates')]
#[Description('Возвращает список доступных версий шаблонов с описанием и упорядоченным списком шагов.')]
#[IsReadOnly]
#[IsIdempotent]
class ListPipelineTemplatesTool extends Tool
{
    public function __construct(
        private readonly McpModelResolver $mcpModelResolver,
        private readonly GetPipelineTemplatesCatalogAction $getPipelineTemplatesCatalogAction,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $viewer = $this->mcpModelResolver->user($request);

        return Response::structured([
            'pipeline_versions' => $this->getPipelineTemplatesCatalogAction->handle($viewer),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
