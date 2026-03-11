<?php

namespace App\Mcp\Resources;

use App\Actions\Project\BuildProjectStepResultsSingleFileAction;
use App\Mcp\Support\McpModelResolver;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('project-single-file-pdf-export')]
#[MimeType('application/pdf')]
#[Description('Возвращает единый PDF-файл экспорта проекта по выбранной версии шаблона и шагу.')]
class ProjectSingleFilePdfExportResource extends Resource implements HasUriTemplate
{
    public function __construct(
        private readonly McpModelResolver $mcpModelResolver,
        private readonly BuildProjectStepResultsSingleFileAction $buildProjectStepResultsSingleFileAction,
    ) {}

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate(
            'video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/single-file/pdf'
        );
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'pipeline_version_id' => ['required', 'integer'],
            'step_version_id' => ['required', 'integer'],
        ]);

        $viewer = $this->mcpModelResolver->user($request);
        $project = $this->mcpModelResolver->visibleProject($viewer, (int) $validated['project_id']);
        $this->mcpModelResolver->allowedPipelineVersionId(
            $viewer,
            (int) $validated['pipeline_version_id'],
            'pipeline_version_id',
        );

        $download = $this->buildProjectStepResultsSingleFileAction->handle(
            project: $project,
            pipelineVersionId: (int) $validated['pipeline_version_id'],
            stepVersionId: (int) $validated['step_version_id'],
            format: 'pdf',
        );

        try {
            $content = File::get($download['file_path']);

            if (! is_string($content)) {
                throw ValidationException::withMessages([
                    'project_id' => 'Не удалось прочитать сформированный файл проекта.',
                ]);
            }
        } finally {
            File::deleteDirectory($download['cleanup_dir']);
        }

        return Response::blob($content);
    }
}
