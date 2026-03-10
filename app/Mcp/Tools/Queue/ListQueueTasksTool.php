<?php

namespace App\Mcp\Tools\Queue;

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

#[Name('list-queue-tasks')]
#[Description('Возвращает список задач в очереди обработки со статусами.')]
#[IsReadOnly]
#[IsIdempotent]
class ListQueueTasksTool extends Tool
{
    public function __construct(
        private readonly McpModelResolver $mcpModelResolver,
        private readonly McpPresenter $mcpPresenter,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $this->mcpModelResolver->user($request);

        return Response::structured([
            'queue' => $this->mcpPresenter->queue(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
