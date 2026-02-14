<?php

namespace App\Services\Pipeline;

use App\Models\Pipeline;

class PipelineDetailsQuery
{
    public function get(Pipeline $pipeline): Pipeline
    {
        /** @var Pipeline $resolvedPipeline */
        $resolvedPipeline = Pipeline::query()
            ->whereKey($pipeline->id)
            ->with([
                'currentVersion:id,pipeline_id,title,version,description,status',
                'versions' => fn ($query) => $query
                    ->select(['id', 'pipeline_id', 'version', 'title', 'description', 'status'])
                    ->orderByDesc('version'),
                'versions.versionSteps' => fn ($query) => $query
                    ->select(['id', 'pipeline_version_id', 'step_version_id', 'position'])
                    ->orderBy('position'),
                'versions.versionSteps.stepVersion' => fn ($query) => $query
                    ->select(['id', 'step_id', 'input_step_id', 'name', 'type', 'version', 'description', 'settings']),
                'versions.versionSteps.stepVersion.inputStep.currentVersion' => fn ($query) => $query
                    ->select(['id', 'step_id', 'name']),
            ])
            ->firstOrFail();

        return $resolvedPipeline;
    }
}
