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

#[Name('get-run-step-result')]
#[Description('Возвращает результат выбранного шага прогона.')]
class GetRunStepResultTool extends Tool
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
            'step_id' => ['required', 'integer', 'min:1'],
        ], attributes: [
            'run_id' => 'прогон',
            'step_id' => 'шаг',
        ]);

        $viewer = $this->mcpModelResolver->user($request);
        $run = $this->mcpModelResolver->visibleRun($viewer, (int) $validated['run_id']);
        $run = $this->projectRunDetailsQuery->get($run);
        $step = $this->mcpModelResolver->stepForRun($run, (int) $validated['step_id']);

        return Response::structured([
            'run' => $this->mcpPresenter->run($run),
            'step' => $this->mcpPresenter->step($step, includeResult: true),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'run_id' => $schema->integer()
                ->required()
                ->description('ID прогона, в котором находится шаг.'),
            'step_id' => $schema->integer()
                ->required()
                ->description('ID шага, результат которого нужно вернуть.'),
        ];
    }
}
