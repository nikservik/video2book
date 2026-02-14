<?php

namespace App\Actions\Pipeline;

use App\Models\PipelineVersion;

class TogglePipelineVersionArchiveStatusAction
{
    public function handle(PipelineVersion $pipelineVersion): PipelineVersion
    {
        $nextStatus = $pipelineVersion->status === 'archived' ? 'active' : 'archived';

        $pipelineVersion->update([
            'status' => $nextStatus,
        ]);

        return $pipelineVersion->refresh();
    }
}
