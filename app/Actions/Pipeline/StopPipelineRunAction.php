<?php

namespace App\Actions\Pipeline;

use App\Models\PipelineRun;
use App\Services\Pipeline\PipelineRunService;

class StopPipelineRunAction
{
    public function __construct(private readonly PipelineRunService $pipelineRunService) {}

    public function handle(PipelineRun $pipelineRun): PipelineRun
    {
        return $this->pipelineRunService->stop($pipelineRun);
    }
}
