<?php

namespace App\Mcp\Tools\Runs;

use App\Mcp\Support\McpModelResolver;
use App\Mcp\Support\McpPresenter;
use App\Services\Project\LessonRunsQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-lesson-runs')]
#[Description('Возвращает список прогонов выбранного урока.')]
#[IsReadOnly]
#[IsIdempotent]
class ListLessonRunsTool extends Tool
{
    public function __construct(
        private readonly McpModelResolver $mcpModelResolver,
        private readonly LessonRunsQuery $lessonRunsQuery,
        private readonly McpPresenter $mcpPresenter,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'lesson_id' => ['required', 'integer', 'min:1'],
        ], attributes: [
            'lesson_id' => 'урок',
        ]);

        $viewer = $this->mcpModelResolver->user($request);
        $lesson = $this->mcpModelResolver->visibleLesson($viewer, (int) $validated['lesson_id']);
        $runs = $this->lessonRunsQuery->get($lesson);
        $lesson->setRelation('pipelineRuns', $runs);

        return Response::structured([
            'lesson' => $this->mcpPresenter->lesson($lesson),
            'runs' => $runs
                ->map(fn ($run): array => $this->mcpPresenter->run($run))
                ->values()
                ->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'lesson_id' => $schema->integer()
                ->required()
                ->description('ID урока, для которого нужен список прогонов.'),
        ];
    }
}
