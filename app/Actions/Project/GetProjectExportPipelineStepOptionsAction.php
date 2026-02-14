<?php

namespace App\Actions\Project;

use App\Models\PipelineRun;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Project;

class GetProjectExportPipelineStepOptionsAction
{
    /**
     * @return array<int, array{id:int,label:string,steps:array<int, array{id:int,name:string}>}>
     */
    public function handle(Project $project): array
    {
        $pipelineVersionIds = PipelineRun::query()
            ->whereHas('lesson', fn ($query) => $query->where('project_id', $project->id))
            ->where('status', 'done')
            ->pluck('pipeline_version_id')
            ->unique()
            ->values()
            ->all();

        if ($pipelineVersionIds === []) {
            return [];
        }

        return PipelineVersion::query()
            ->whereIn('id', $pipelineVersionIds)
            ->with([
                'versionSteps' => fn ($query) => $query
                    ->orderBy('position')
                    ->with('stepVersion:id,name,type'),
            ])
            ->orderBy('id')
            ->get(['id', 'title', 'version'])
            ->map(function (PipelineVersion $pipelineVersion): ?array {
                $textSteps = $pipelineVersion->versionSteps
                    ->map(fn (PipelineVersionStep $versionStep) => $versionStep->stepVersion)
                    ->filter(fn ($stepVersion): bool => $stepVersion !== null && $stepVersion->type === 'text')
                    ->unique('id')
                    ->values()
                    ->map(fn ($stepVersion): array => [
                        'id' => $stepVersion->id,
                        'name' => $stepVersion->name ?? 'Без названия',
                    ])
                    ->all();

                if ($textSteps === []) {
                    return null;
                }

                return [
                    'id' => $pipelineVersion->id,
                    'label' => sprintf(
                        '%s • v%s',
                        $pipelineVersion->title ?? 'Без названия',
                        (string) $pipelineVersion->version
                    ),
                    'steps' => $textSteps,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
