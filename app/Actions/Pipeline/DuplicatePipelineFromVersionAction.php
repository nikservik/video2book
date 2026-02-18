<?php

namespace App\Actions\Pipeline;

use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\StepVersion;
use Illuminate\Support\Facades\DB;

class DuplicatePipelineFromVersionAction
{
    public function handle(PipelineVersion $sourceVersion, string $title): Pipeline
    {
        return DB::transaction(function () use ($sourceVersion, $title): Pipeline {
            $sourceVersion = PipelineVersion::query()
                ->whereKey($sourceVersion->id)
                ->with([
                    'versionSteps' => fn ($query) => $query
                        ->select(['id', 'pipeline_version_id', 'step_version_id', 'position'])
                        ->orderBy('position'),
                    'versionSteps.stepVersion' => fn ($query) => $query
                        ->select(['id', 'step_id', 'input_step_id', 'name', 'type', 'version', 'description', 'prompt', 'settings', 'status']),
                ])
                ->firstOrFail();

            $pipeline = Pipeline::query()->create();

            $pipelineVersion = $pipeline->versions()->create([
                'version' => 1,
                'title' => trim($title),
                'description' => $sourceVersion->description,
                'changelog' => $sourceVersion->changelog,
                'created_by' => null,
                'status' => $sourceVersion->status,
            ]);

            $pipeline->update(['current_version_id' => $pipelineVersion->id]);

            /** @var array<int, int> $newStepIdBySourceStepId */
            $newStepIdBySourceStepId = [];

            /** @var array<int, array{step_version_id:int, source_input_step_id:int|null}> $copiedStepVersions */
            $copiedStepVersions = [];

            foreach ($sourceVersion->versionSteps as $versionStep) {
                $sourceStepVersion = $versionStep->stepVersion;

                if ($sourceStepVersion === null) {
                    continue;
                }

                $step = $pipeline->steps()->create();

                $newStepIdBySourceStepId[(int) $sourceStepVersion->step_id] = (int) $step->id;

                $stepVersion = $step->versions()->create([
                    'input_step_id' => null,
                    'name' => (string) $sourceStepVersion->name,
                    'type' => (string) $sourceStepVersion->type,
                    'version' => 1,
                    'description' => $sourceStepVersion->description,
                    'prompt' => $sourceStepVersion->prompt,
                    'settings' => is_array($sourceStepVersion->settings) ? $sourceStepVersion->settings : [],
                    'status' => (string) $sourceStepVersion->status,
                ]);

                $step->update(['current_version_id' => $stepVersion->id]);

                $copiedStepVersions[] = [
                    'step_version_id' => (int) $stepVersion->id,
                    'source_input_step_id' => $sourceStepVersion->input_step_id === null
                        ? null
                        : (int) $sourceStepVersion->input_step_id,
                ];

                PipelineVersionStep::query()->create([
                    'pipeline_version_id' => $pipelineVersion->id,
                    'step_version_id' => $stepVersion->id,
                    'position' => (int) $versionStep->position,
                ]);
            }

            foreach ($copiedStepVersions as $copiedStepVersion) {
                $sourceInputStepId = $copiedStepVersion['source_input_step_id'];

                $resolvedInputStepId = $sourceInputStepId === null
                    ? null
                    : ($newStepIdBySourceStepId[$sourceInputStepId] ?? null);

                StepVersion::query()
                    ->whereKey($copiedStepVersion['step_version_id'])
                    ->update(['input_step_id' => $resolvedInputStepId]);
            }

            return $pipeline->fresh();
        });
    }
}
