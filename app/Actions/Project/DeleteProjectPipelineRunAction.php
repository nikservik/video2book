<?php

namespace App\Actions\Project;

use App\Models\PipelineRun;
use App\Models\Project;

class DeleteProjectPipelineRunAction
{
    public function handle(Project $project, int $pipelineRunId): void
    {
        $pipelineRun = PipelineRun::query()
            ->whereKey($pipelineRunId)
            ->whereHas('lesson', fn ($query) => $query->where('project_id', $project->id))
            ->firstOrFail();

        $pipelineRun->delete();
    }
}
