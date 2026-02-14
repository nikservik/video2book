<?php

namespace App\Services\Project;

use App\Models\Project;

class ProjectDetailsQuery
{
    public function get(Project $project): Project
    {
        return $project->load([
            'lessons' => fn ($query) => $query
                ->with([
                    'pipelineRuns' => fn ($runQuery) => $runQuery
                        ->with([
                            'pipelineVersion:id,title,version',
                        ])
                        ->orderByDesc('id')
                        ->select(['id', 'lesson_id', 'pipeline_version_id', 'status']),
                ])
                ->orderBy('created_at')
                ->orderBy('id')
                ->select(['id', 'project_id', 'name', 'created_at']),
        ]);
    }
}
