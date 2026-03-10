<?php

namespace App\Mcp\Tools\Runs;

use App\Actions\Pipeline\RestartPipelineRunFromStepAction;
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

#[Name('restart-run-step')]
#[Description('Перезапускает выбранный шаг прогона и все следующие шаги через стандартный pipeline restart-flow.')]
class RestartRunStepTool extends Tool
{
    public function __construct(
        private readonly McpModelResolver $mcpModelResolver,
        private readonly RestartPipelineRunFromStepAction $restartPipelineRunFromStepAction,
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
        $step = $this->mcpModelResolver->stepForRun($run, (int) $validated['step_id']);

        $run = $this->restartPipelineRunFromStepAction->handle($run, $step);
        $run = $this->projectRunDetailsQuery->get($run);

        return Response::structured([
            'run' => $this->mcpPresenter->run($run),
            'steps' => $run->steps
                ->map(fn ($runStep): array => $this->mcpPresenter->step($runStep))
                ->values()
                ->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'run_id' => $schema->integer()
                ->required()
                ->description('ID прогона, в котором нужно перезапустить шаг.'),
            'step_id' => $schema->integer()
                ->required()
                ->description('ID шага, начиная с которого нужно выполнить перезапуск.'),
        ];
    }
}
