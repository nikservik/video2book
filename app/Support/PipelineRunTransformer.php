<?php

namespace App\Support;

use App\Models\PipelineRun;
use App\Models\PipelineRunStep;

final class PipelineRunTransformer
{
    public static function run(PipelineRun $run, bool $includeResults = true): array
    {
        $run->loadMissing('lesson.project', 'pipelineVersion', 'steps.stepVersion.step');

        return [
            'id' => $run->id,
            'status' => $run->status,
            'state' => $run->state ?? [],
            'lesson' => $run->lesson
                ? [
                    'id' => $run->lesson->id,
                    'name' => $run->lesson->name,
                    'project' => $run->lesson->project
                        ? [
                            'id' => $run->lesson->project->id,
                            'name' => $run->lesson->project->name,
                        ]
                        : null,
                ]
                : null,
            'pipeline_version' => $run->pipelineVersion
                ? [
                    'id' => $run->pipelineVersion->id,
                    'title' => $run->pipelineVersion->title,
                    'version' => $run->pipelineVersion->version,
                ]
                : null,
            'steps' => $run->steps
                ->sortBy('position')
                ->values()
                ->map(fn (PipelineRunStep $step): array => self::step($step, $includeResults))
                ->all(),
            'created_at' => optional($run->created_at)->toISOString(),
            'updated_at' => optional($run->updated_at)->toISOString(),
        ];
    }

    public static function step(PipelineRunStep $step, bool $includeResults = true): array
    {
        $step->loadMissing('stepVersion.step');

        $stepVersion = $step->stepVersion;

        $payload = [
            'id' => $step->id,
            'position' => $step->position,
            'status' => $step->status,
            'name' => $stepVersion?->name,
            'type' => $stepVersion?->type,
            'start_time' => optional($step->start_time)->toISOString(),
            'end_time' => optional($step->end_time)->toISOString(),
            'input_tokens' => $step->input_tokens,
            'output_tokens' => $step->output_tokens,
            'cost' => $step->cost,
        ];

        if ($includeResults) {
            $payload['result'] = $step->result;
            $payload['error'] = $step->error;
        }

        return $payload;
    }
}
