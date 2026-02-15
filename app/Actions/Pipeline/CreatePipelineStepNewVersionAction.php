<?php

namespace App\Actions\Pipeline;

use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\StepVersion;
use Illuminate\Support\Facades\DB;

class CreatePipelineStepNewVersionAction
{
    /**
     * @param  array{name:string,type:string,description:?string,prompt:?string,settings:array<string,mixed>,input_step_id:?int}  $payload
     */
    public function handle(
        Pipeline $pipeline,
        PipelineVersion $sourceVersion,
        StepVersion $sourceStepVersion,
        array $payload,
        string $changelogEntry,
        bool $setAsCurrent,
    ): PipelineVersion {
        return DB::transaction(function () use ($pipeline, $sourceVersion, $sourceStepVersion, $payload, $changelogEntry, $setAsCurrent): PipelineVersion {
            $step = $sourceStepVersion->step;

            if ($step === null || (int) $step->pipeline_id !== (int) $pipeline->id) {
                return $sourceVersion;
            }

            $nextStepVersionNumber = ((int) $step->versions()->max('version')) + 1;

            $newStepVersion = $step->versions()->create([
                'name' => $payload['name'],
                'type' => $payload['type'],
                'version' => $nextStepVersionNumber,
                'description' => $payload['description'],
                'prompt' => $payload['prompt'],
                'settings' => $payload['settings'],
                'status' => $sourceStepVersion->status,
                'input_step_id' => $payload['input_step_id'],
            ]);

            $step->update(['current_version_id' => $newStepVersion->id]);

            $nextPipelineVersionNumber = ((int) $pipeline->versions()->max('version')) + 1;
            $formattedChangelogEntry = $this->formatStepChangeLogEntry(
                stepName: (string) $payload['name'],
                changelogEntry: $changelogEntry,
            );

            $newPipelineVersion = $pipeline->versions()->create([
                'version' => $nextPipelineVersionNumber,
                'title' => $sourceVersion->title,
                'description' => $sourceVersion->description,
                'changelog' => $this->composeChangelog($sourceVersion->changelog, $formattedChangelogEntry),
                'created_by' => $sourceVersion->created_by,
                'status' => $sourceVersion->status,
            ]);

            $sourceVersion->loadMissing('versionSteps.stepVersion');

            foreach ($sourceVersion->versionSteps->sortBy('position') as $versionStep) {
                $versionStepStepVersion = $versionStep->stepVersion;

                $stepVersionId = (int) $versionStep->step_version_id;

                if ($versionStepStepVersion !== null && (int) $versionStepStepVersion->step_id === (int) $step->id) {
                    $stepVersionId = $newStepVersion->id;
                }

                PipelineVersionStep::query()->create([
                    'pipeline_version_id' => $newPipelineVersion->id,
                    'step_version_id' => $stepVersionId,
                    'position' => (int) $versionStep->position,
                ]);
            }

            if ($setAsCurrent) {
                $pipeline->update(['current_version_id' => $newPipelineVersion->id]);
            }

            return $newPipelineVersion->refresh();
        });
    }

    private function composeChangelog(?string $existing, string $entry): string
    {
        return collect([$existing, $entry])
            ->filter(fn (?string $value): bool => ! empty(trim((string) $value)))
            ->implode("\n");
    }

    private function formatStepChangeLogEntry(string $stepName, string $changelogEntry): string
    {
        return sprintf(
            '- Изменения в шаге «%s»: %s',
            trim($stepName),
            trim($changelogEntry),
        );
    }
}
