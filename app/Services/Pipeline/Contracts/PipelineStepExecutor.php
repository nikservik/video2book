<?php

namespace App\Services\Pipeline\Contracts;

use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Services\Pipeline\PipelineStepResult;

interface PipelineStepExecutor
{
    public function execute(PipelineRun $run, PipelineRunStep $step, ?string $input): PipelineStepResult;
}
