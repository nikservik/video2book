<?php

namespace App\Mcp\Tools\Lessons;

use App\Actions\Project\CreateProjectLessonFromYoutubeAction;
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

#[Name('create-project-lesson-from-url')]
#[Description('Добавляет в проект новый урок по YouTube-ссылке и запускает стандартный download-flow.')]
class CreateProjectLessonFromUrlTool extends Tool
{
    public function __construct(
        private readonly McpModelResolver $mcpModelResolver,
        private readonly CreateProjectLessonFromYoutubeAction $createProjectLessonFromYoutubeAction,
        private readonly LessonRunsQuery $lessonRunsQuery,
        private readonly McpPresenter $mcpPresenter,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'min:1'],
            'lesson_name' => ['required', 'string', 'max:255'],
            'youtube_url' => ['required', 'url', 'starts_with:https://'],
            'pipeline_version_id' => ['required', 'integer', 'min:1'],
        ], attributes: [
            'project_id' => 'проект',
            'lesson_name' => 'название урока',
            'youtube_url' => 'ссылка на YouTube',
            'pipeline_version_id' => 'версия шаблона',
        ]);

        $viewer = $this->mcpModelResolver->user($request);
        $project = $this->mcpModelResolver->visibleProject($viewer, (int) $validated['project_id']);
        $pipelineVersionId = $this->mcpModelResolver->allowedPipelineVersionId(
            $viewer,
            (int) $validated['pipeline_version_id'],
        );

        $lesson = $this->createProjectLessonFromYoutubeAction->handle(
            project: $project,
            lessonName: trim($validated['lesson_name']),
            youtubeUrl: $validated['youtube_url'],
            pipelineVersionId: (int) $pipelineVersionId,
        );

        $lesson->setRelation('pipelineRuns', $this->lessonRunsQuery->get($lesson));

        return Response::structured([
            'lesson' => $this->mcpPresenter->lesson($lesson),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->required()
                ->description('ID проекта, в который нужно добавить урок.'),
            'lesson_name' => $schema->string()
                ->required()
                ->max(255)
                ->description('Название нового урока.'),
            'youtube_url' => $schema->string()
                ->required()
                ->format('uri')
                ->description('HTTPS-ссылка на видео для создания урока.'),
            'pipeline_version_id' => $schema->integer()
                ->required()
                ->description('ID версии шаблона для нового урока.'),
        ];
    }
}
