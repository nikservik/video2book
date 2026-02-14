<?php

namespace App\Services\Project;

use App\Models\PipelineRun;

class ProjectRunDetailsQuery
{
    public function get(PipelineRun $pipelineRun): PipelineRun
    {
        return $pipelineRun->load([
            'lesson:id,project_id,name',
            'pipelineVersion:id,title,version',
            'steps' => fn ($query) => $query
                ->with([
                    'stepVersion:id,name',
                ])
                ->orderBy('position')
                ->select([
                    'id',
                    'pipeline_run_id',
                    'step_version_id',
                    'position',
                    'status',
                    'result',
                    'input_tokens',
                    'output_tokens',
                    'cost',
                ]),
        ]);
    }
}
