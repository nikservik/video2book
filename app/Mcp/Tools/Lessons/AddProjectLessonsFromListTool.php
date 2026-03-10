<?php

namespace App\Mcp\Tools\Lessons;

use App\Actions\Project\AddLessonsListToProjectAction;
use App\Mcp\Support\McpModelResolver;
use App\Mcp\Support\McpPresenter;
use App\Services\Project\ProjectDetailsQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('add-project-lessons-from-list')]
#[Description('Добавляет в проект несколько уроков из списка названий и https:// ссылок.')]
class AddProjectLessonsFromListTool extends Tool
{
    public function __construct(
        private readonly McpModelResolver $mcpModelResolver,
        private readonly AddLessonsListToProjectAction $addLessonsListToProjectAction,
        private readonly ProjectDetailsQuery $projectDetailsQuery,
        private readonly McpPresenter $mcpPresenter,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'lessons_list' => ['required', 'string'],
        ], attributes: [
            'project_id' => 'проект',
            'lessons_list' => 'список уроков',
        ]);

        $viewer = $this->mcpModelResolver->user($request);
        $project = $this->mcpModelResolver->visibleProject($viewer, (int) $validated['project_id']);

        try {
            $this->addLessonsListToProjectAction->handle(
                project: $project,
                lessonsList: $validated['lessons_list'],
            );
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->normalizeValidationErrors($exception));
        }

        $project = $this->projectDetailsQuery->get($project->fresh());

        return Response::structured([
            'project' => $this->mcpPresenter->project($project),
            'lessons' => $project->lessons
                ->map(fn ($lesson): array => $this->mcpPresenter->lesson($lesson))
                ->values()
                ->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->required()
                ->description('ID проекта.'),
            'lessons_list' => $schema->string()
                ->required()
                ->description('Список уроков: название урока и следующей строкой https:// ссылка.'),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function normalizeValidationErrors(ValidationException $exception): array
    {
        $normalizedErrors = [];

        foreach ($exception->errors() as $field => $messages) {
            $normalizedErrors[$field === 'newLessonsList' ? 'lessons_list' : $field] = $messages;
        }

        return $normalizedErrors;
    }
}
