<?php

namespace App\Actions\Project;

use App\Models\Project;

class UpdateProjectNameAction
{
    public function handle(Project $project, string $name, ?string $referer = null, ?int $defaultPipelineVersionId = null): void
    {
        $project->update([
            'name' => $name,
            'referer' => $referer,
            'default_pipeline_version_id' => $defaultPipelineVersionId,
        ]);
    }
}
