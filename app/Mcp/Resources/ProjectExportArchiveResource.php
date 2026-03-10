<?php

namespace App\Mcp\Resources;

use App\Actions\Project\BuildProjectStepResultsArchiveAction;
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

#[Name('project-export-archive')]
#[MimeType('application/zip')]
#[Description('Возвращает ZIP-архив экспорта проекта по выбранной версии шаблона, шагу и формату.')]
class ProjectExportArchiveResource extends Resource implements HasUriTemplate
{
    public function __construct(
        private readonly McpModelResolver $mcpModelResolver,
        private readonly BuildProjectStepResultsArchiveAction $buildProjectStepResultsArchiveAction,
    ) {}

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate(
            'video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/{format}/{archive_file_naming}'
        );
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'pipeline_version_id' => ['required', 'integer'],
            'step_version_id' => ['required', 'integer'],
            'format' => ['required', 'string', 'in:pdf,md,docx'],
            'archive_file_naming' => ['required', 'string', 'in:lesson,lesson_step'],
        ]);

        $viewer = $this->mcpModelResolver->user($request);
        $project = $this->mcpModelResolver->visibleProject($viewer, (int) $validated['project_id']);
        $this->mcpModelResolver->allowedPipelineVersionId(
            $viewer,
            (int) $validated['pipeline_version_id'],
            'pipeline_version_id',
        );

        $archive = $this->buildProjectStepResultsArchiveAction->handle(
            project: $project,
            pipelineVersionId: (int) $validated['pipeline_version_id'],
            stepVersionId: (int) $validated['step_version_id'],
            format: $validated['format'],
            archiveFileNaming: $validated['archive_file_naming'],
        );

        try {
            $content = File::get($archive['archive_path']);

            if (! is_string($content)) {
                throw ValidationException::withMessages([
                    'project_id' => 'Не удалось прочитать сформированный архив проекта.',
                ]);
            }
        } finally {
            File::deleteDirectory($archive['cleanup_dir']);
        }

        return Response::blob($content);
    }
}
