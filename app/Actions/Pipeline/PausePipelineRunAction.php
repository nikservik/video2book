<?php

namespace App\Actions\Pipeline;

use App\Models\PipelineRun;
use App\Services\Pipeline\PipelineRunService;

class PausePipelineRunAction
{
    public function __construct(private readonly PipelineRunService $pipelineRunService) {}

    public function handle(PipelineRun $pipelineRun): PipelineRun
    {
        return $this->pipelineRunService->pause($pipelineRun);
    }
}
