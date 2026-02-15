<?php

namespace App\Actions\Pipeline;

use App\Models\Pipeline;
use App\Models\PipelineVersion;

class SetCurrentPipelineVersionAction
{
    public function handle(Pipeline $pipeline, PipelineVersion $pipelineVersion): Pipeline
    {
        if ((int) $pipelineVersion->pipeline_id !== (int) $pipeline->id) {
            return $pipeline->refresh();
        }

        $pipeline->update([
            'current_version_id' => $pipelineVersion->id,
        ]);

        return $pipeline->refresh();
    }
}
