<?php

namespace App\Actions\Pipeline;

use App\Models\PipelineVersion;

class UpdatePipelineVersionAction
{
    public function handle(PipelineVersion $pipelineVersion, string $title, ?string $description): PipelineVersion
    {
        $pipelineVersion->update([
            'title' => trim($title),
            'description' => $description,
        ]);

        return $pipelineVersion->refresh();
    }
}
