<?php

namespace App\Actions\Pipeline;

use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use Illuminate\Support\Facades\DB;

class AddPipelineStepToVersionAction
{
    /**
     * @param  array{name:string,type:string,description:?string,prompt:?string,settings:array<string,mixed>,input_step_id:?int}  $payload
     */
    public function handle(
        Pipeline $pipeline,
        PipelineVersion $sourceVersion,
        int $position,
        array $payload,
        bool $setAsCurrent,
    ): PipelineVersion {
        return DB::transaction(function () use ($pipeline, $sourceVersion, $position, $payload, $setAsCurrent): PipelineVersion {
            $sourceVersion = PipelineVersion::query()
                ->whereKey($sourceVersion->id)
                ->with('versionSteps.stepVersion')
                ->firstOrFail();

            if ((int) $sourceVersion->pipeline_id !== (int) $pipeline->id) {
                return $sourceVersion;
            }

            $step = $pipeline->steps()->create();

            $stepVersion = $step->versions()->create([
                'name' => trim($payload['name']),
                'type' => $payload['type'],
                'version' => 1,
                'description' => $payload['description'],
                'prompt' => $payload['prompt'],
                'settings' => $payload['settings'],
                'status' => 'active',
                'input_step_id' => $payload['input_step_id'],
            ]);

            $step->update(['current_version_id' => $stepVersion->id]);

            $versionStepsPayload = $sourceVersion->versionSteps
                ->sortBy('position')
                ->map(fn (PipelineVersionStep $versionStep): array => [
                    'step_version_id' => (int) $versionStep->step_version_id,
                ])
                ->values()
                ->all();

            $normalizedPosition = max(1, min($position, count($versionStepsPayload) + 1));
            array_splice($versionStepsPayload, $normalizedPosition - 1, 0, [[
                'step_version_id' => (int) $stepVersion->id,
            ]]);

            $nextPipelineVersionNumber = ((int) $pipeline->versions()->max('version')) + 1;

            $newPipelineVersion = $pipeline->versions()->create([
                'version' => $nextPipelineVersionNumber,
                'title' => $sourceVersion->title,
                'description' => $sourceVersion->description,
                'changelog' => $this->composeChangelog(
                    $sourceVersion->changelog,
                    $this->formatAddedStepChangeLogEntry(
                        stepName: trim($payload['name']),
                        shortDescription: $payload['description'],
                    ),
                ),
                'created_by' => $sourceVersion->created_by,
                'status' => $sourceVersion->status,
            ]);

            foreach ($versionStepsPayload as $index => $stepPayload) {
                PipelineVersionStep::query()->create([
                    'pipeline_version_id' => $newPipelineVersion->id,
                    'step_version_id' => $stepPayload['step_version_id'],
                    'position' => $index + 1,
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

    private function formatAddedStepChangeLogEntry(string $stepName, ?string $shortDescription): string
    {
        $normalizedDescription = trim((string) $shortDescription);

        if ($normalizedDescription === '') {
            $normalizedDescription = 'без описания';
        }

        return sprintf(
            '- Добавлен шаг «%s»: %s',
            $stepName,
            $normalizedDescription,
        );
    }
}
