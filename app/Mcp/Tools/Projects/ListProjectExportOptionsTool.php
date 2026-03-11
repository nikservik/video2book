<?php

namespace App\Mcp\Tools\Projects;

use App\Actions\Project\GetProjectExportPipelineStepOptionsAction;
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

#[Name('list-project-export-options')]
#[Description('Возвращает доступные варианты экспорта проекта: версии шаблонов и текстовые шаги.')]
#[IsReadOnly]
#[IsIdempotent]
class ListProjectExportOptionsTool extends Tool
{
    public function __construct(
        private readonly McpModelResolver $mcpModelResolver,
        private readonly GetProjectExportPipelineStepOptionsAction $getProjectExportPipelineStepOptionsAction,
        private readonly McpPresenter $mcpPresenter,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
        ]);

        $viewer = $this->mcpModelResolver->user($request);
        $project = $this->mcpModelResolver->visibleProject($viewer, (int) $validated['project_id']);

        return Response::structured([
            'project' => $this->mcpPresenter->project($project),
            'download_modes' => $this->downloadModes(),
            'pipeline_versions' => $this->getProjectExportPipelineStepOptionsAction->handle($project),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->required()
                ->description('ID проекта, для которого нужно показать опции экспорта.'),
        ];
    }

    /**
     * @return array<int, array{
     *     id:string,
     *     label:string,
     *     default:bool,
     *     description:string,
     *     formats:array<int, array{id:string,resource_uri_template:string}>
     * }>
     */
    private function downloadModes(): array
    {
        return [
            [
                'id' => 'single_file',
                'label' => 'Одним файлом',
                'default' => true,
                'description' => 'Объединяет результаты всех подходящих уроков проекта в один файл. Перед каждым уроком добавляется заголовок первого уровня, внутренние заголовки шага сдвигаются на один уровень глубже.',
                'formats' => [
                    [
                        'id' => 'md',
                        'resource_uri_template' => 'video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/single-file/markdown',
                    ],
                    [
                        'id' => 'pdf',
                        'resource_uri_template' => 'video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/single-file/pdf',
                    ],
                    [
                        'id' => 'docx',
                        'resource_uri_template' => 'video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/single-file/docx',
                    ],
                ],
            ],
            [
                'id' => 'lesson',
                'label' => 'Урок',
                'default' => false,
                'description' => 'Возвращает ZIP-архив, где для каждого урока создаётся отдельный файл.',
                'formats' => [
                    [
                        'id' => 'md',
                        'resource_uri_template' => 'video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/md/lesson',
                    ],
                    [
                        'id' => 'pdf',
                        'resource_uri_template' => 'video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/pdf/lesson',
                    ],
                    [
                        'id' => 'docx',
                        'resource_uri_template' => 'video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/docx/lesson',
                    ],
                ],
            ],
            [
                'id' => 'lesson_step',
                'label' => 'Урок - шаг',
                'default' => false,
                'description' => 'Возвращает ZIP-архив, где для каждого урока создаётся отдельный файл с названием урока и шага.',
                'formats' => [
                    [
                        'id' => 'md',
                        'resource_uri_template' => 'video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/md/lesson_step',
                    ],
                    [
                        'id' => 'pdf',
                        'resource_uri_template' => 'video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/pdf/lesson_step',
                    ],
                    [
                        'id' => 'docx',
                        'resource_uri_template' => 'video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/docx/lesson_step',
                    ],
                ],
            ],
        ];
    }
}
