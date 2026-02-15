<?php

namespace App\Actions\Pipeline;

use App\Models\PipelineVersion;

class TogglePipelineVersionArchiveStatusAction
{
    public function handle(PipelineVersion $pipelineVersion): PipelineVersion
    {
        if ($pipelineVersion->status === 'archived' && $this->hasDraftSteps($pipelineVersion)) {
            return $pipelineVersion->refresh();
        }

        $nextStatus = $pipelineVersion->status === 'archived' ? 'active' : 'archived';

        $pipelineVersion->update([
            'status' => $nextStatus,
        ]);

        return $pipelineVersion->refresh();
    }

    private function hasDraftSteps(PipelineVersion $pipelineVersion): bool
    {
        return $pipelineVersion->versionSteps()
            ->whereHas('stepVersion', fn ($query) => $query->where('status', 'draft'))
            ->exists();
    }
}
