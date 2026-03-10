<?php

namespace App\Mcp\Tools\Runs;

use App\Mcp\Support\McpModelResolver;
use App\Mcp\Support\McpPresenter;
use App\Services\Project\ProjectRunDetailsQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-run-steps')]
#[Description('Возвращает шаги выбранного прогона с текущими статусами.')]
#[IsReadOnly]
#[IsIdempotent]
class ListRunStepsTool extends Tool
{
    public function __construct(
        private readonly McpModelResolver $mcpModelResolver,
        private readonly ProjectRunDetailsQuery $projectRunDetailsQuery,
        private readonly McpPresenter $mcpPresenter,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'run_id' => ['required', 'integer', 'min:1'],
        ], attributes: [
            'run_id' => 'прогон',
        ]);

        $viewer = $this->mcpModelResolver->user($request);
        $run = $this->mcpModelResolver->visibleRun($viewer, (int) $validated['run_id']);
        $run = $this->projectRunDetailsQuery->get($run);

        return Response::structured([
            'run' => $this->mcpPresenter->run($run),
            'steps' => $run->steps
                ->map(fn ($step): array => $this->mcpPresenter->step($step))
                ->values()
                ->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'run_id' => $schema->integer()
                ->required()
                ->description('ID прогона, для которого нужно вернуть список шагов.'),
        ];
    }
}
