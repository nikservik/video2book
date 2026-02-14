<?php

namespace App\Actions\Pipeline;

use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Services\Pipeline\PipelineRunService;

class RestartPipelineRunFromStepAction
{
    public function __construct(private readonly PipelineRunService $pipelineRunService) {}

    public function handle(PipelineRun $pipelineRun, PipelineRunStep $step): PipelineRun
    {
        return $this->pipelineRunService->restartFromStep($pipelineRun, $step);
    }
}
