<?php

namespace App\Mcp\Tools\Projects;

use App\Actions\Project\RecalculateProjectLessonsAudioDurationAction;
use App\Mcp\Support\McpModelResolver;
use App\Support\AudioDurationLabelFormatter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('recalculate-project-lessons-duration')]
#[Description('Пересчитывает суммарную длительность уроков проекта.')]
class RecalculateProjectLessonsDurationTool extends Tool
{
    public function __construct(
        private readonly McpModelResolver $mcpModelResolver,
        private readonly RecalculateProjectLessonsAudioDurationAction $recalculateProjectLessonsAudioDurationAction,
        private readonly AudioDurationLabelFormatter $audioDurationLabelFormatter,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
        ]);

        $viewer = $this->mcpModelResolver->user($request);
        $project = $this->mcpModelResolver->visibleProject($viewer, (int) $validated['project_id']);
        $totalDurationSeconds = $this->recalculateProjectLessonsAudioDurationAction->handle($project);

        return Response::structured([
            'project_id' => $project->id,
            'total_duration_seconds' => $totalDurationSeconds,
            'total_duration_label' => $this->audioDurationLabelFormatter->format($totalDurationSeconds),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->required()
                ->description('ID проекта, для которого нужно пересчитать длительность уроков.'),
        ];
    }
}
