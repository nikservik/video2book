<?php

namespace App\Mcp\Resources;

use App\Mcp\Support\McpModelResolver;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('run-step-markdown-export')]
#[MimeType('text/markdown')]
#[Description('Возвращает результат шага прогона в формате Markdown.')]
class RunStepMarkdownExportResource extends Resource implements HasUriTemplate
{
    public function __construct(
        private readonly McpModelResolver $mcpModelResolver,
    ) {}

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('video2book://pipeline-runs/{run_id}/steps/{step_id}/export/markdown');
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'run_id' => ['required', 'integer'],
            'step_id' => ['required', 'integer'],
        ]);

        $viewer = $this->mcpModelResolver->user($request);
        $run = $this->mcpModelResolver->visibleRun($viewer, (int) $validated['run_id']);
        $step = $this->mcpModelResolver->stepForRun($run, (int) $validated['step_id'])->load('stepVersion');

        if (blank($step->result)) {
            throw ValidationException::withMessages([
                'step_id' => 'У выбранного шага нет результата для экспорта.',
            ]);
        }

        return Response::text((string) $step->result);
    }
}
