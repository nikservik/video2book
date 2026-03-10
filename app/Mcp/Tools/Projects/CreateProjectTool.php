<?php

namespace App\Mcp\Tools\Projects;

use App\Actions\Project\CreateProjectFromLessonsListAction;
use App\Mcp\Support\McpModelResolver;
use App\Mcp\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create-project')]
#[Description('Создаёт проект в выбранной папке и при необходимости сразу добавляет уроки из списка ссылок.')]
class CreateProjectTool extends Tool
{
    public function __construct(
        private readonly McpModelResolver $mcpModelResolver,
        private readonly CreateProjectFromLessonsListAction $createProjectFromLessonsListAction,
        private readonly McpPresenter $mcpPresenter,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'folder_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'referer' => ['nullable', 'url', 'starts_with:https://'],
            'default_pipeline_version_id' => ['nullable', 'integer'],
            'lessons_list' => ['nullable', 'string'],
        ]);

        $viewer = $this->mcpModelResolver->user($request);
        $folder = $this->mcpModelResolver->visibleFolder($viewer, (int) $validated['folder_id']);
        $defaultPipelineVersionId = $this->mcpModelResolver->allowedPipelineVersionId(
            $viewer,
            isset($validated['default_pipeline_version_id']) ? (int) $validated['default_pipeline_version_id'] : null,
        );

        $project = $this->createProjectFromLessonsListAction->handle(
            folderId: $folder->id,
            projectName: trim($validated['name']),
            referer: $validated['referer'] ?? null,
            defaultPipelineVersionId: $defaultPipelineVersionId,
            lessonsList: $validated['lessons_list'] ?? null,
        )->loadCount('lessons');

        return Response::structured([
            'project' => $this->mcpPresenter->project($project),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'folder_id' => $schema->integer()
                ->required()
                ->description('ID папки для нового проекта.'),
            'name' => $schema->string()
                ->required()
                ->description('Название проекта.')
                ->max(255),
            'referer' => $schema->string()
                ->description('HTTPS referer, который будет использоваться при загрузке аудио уроков проекта.')
                ->nullable()
                ->format('uri'),
            'default_pipeline_version_id' => $schema->integer()
                ->description('Версия шаблона по умолчанию для новых уроков проекта.')
                ->nullable(),
            'lessons_list' => $schema->string()
                ->description('Список уроков в формате: строка с названием, затем строка с https:// ссылкой.')
                ->nullable(),
        ];
    }
}
