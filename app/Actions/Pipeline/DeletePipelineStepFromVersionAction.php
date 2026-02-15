<?php

namespace App\Actions\Pipeline;

use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Step;
use App\Models\StepVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeletePipelineStepFromVersionAction
{
    public function handle(
        Pipeline $pipeline,
        PipelineVersion $sourceVersion,
        StepVersion $removedStepVersion,
        bool $setAsCurrent,
    ): PipelineVersion {
        return DB::transaction(function () use ($pipeline, $sourceVersion, $removedStepVersion, $setAsCurrent): PipelineVersion {
            $sourceVersion = PipelineVersion::query()
                ->whereKey($sourceVersion->id)
                ->with('versionSteps.stepVersion.step')
                ->firstOrFail();

            $removedStep = $removedStepVersion->step;

            if ($removedStep === null || (int) $removedStep->pipeline_id !== (int) $pipeline->id) {
                return $sourceVersion;
            }

            $orderedVersionSteps = $sourceVersion->versionSteps
                ->sortBy('position')
                ->values();

            $removedVersionStep = $orderedVersionSteps
                ->first(fn (PipelineVersionStep $versionStep): bool => (int) $versionStep->step_version_id === (int) $removedStepVersion->id);

            if ($removedVersionStep === null || (int) $removedVersionStep->position === 1) {
                return $sourceVersion;
            }

            $removedStepName = trim((string) ($removedStepVersion->name ?? 'Без названия шага'));
            $changelogEntries = [
                sprintf('- Удален шаг «%s»', $removedStepName),
            ];

            $newVersionStepsPayload = $this->buildVersionStepsPayload(
                orderedVersionSteps: $orderedVersionSteps,
                removedStep: $removedStep,
                removedStepVersion: $removedStepVersion,
                removedStepName: $removedStepName,
                changelogEntries: $changelogEntries,
            );

            $nextPipelineVersionNumber = ((int) $pipeline->versions()->max('version')) + 1;

            $newPipelineVersion = $pipeline->versions()->create([
                'version' => $nextPipelineVersionNumber,
                'title' => $sourceVersion->title,
                'description' => $sourceVersion->description,
                'changelog' => $this->composeChangelog(
                    $sourceVersion->changelog,
                    implode("\n", $changelogEntries),
                ),
                'created_by' => $sourceVersion->created_by,
                'status' => $sourceVersion->status,
            ]);

            foreach ($newVersionStepsPayload as $index => $stepVersionId) {
                PipelineVersionStep::query()->create([
                    'pipeline_version_id' => $newPipelineVersion->id,
                    'step_version_id' => $stepVersionId,
                    'position' => $index + 1,
                ]);
            }

            if ($setAsCurrent) {
                $pipeline->update(['current_version_id' => $newPipelineVersion->id]);
            }

            return $newPipelineVersion->refresh();
        });
    }

    /**
     * @param  Collection<int, PipelineVersionStep>  $orderedVersionSteps
     * @param  array<int, string>  $changelogEntries
     * @return array<int, int>
     */
    private function buildVersionStepsPayload(
        Collection $orderedVersionSteps,
        Step $removedStep,
        StepVersion $removedStepVersion,
        string $removedStepName,
        array &$changelogEntries,
    ): array {
        $stepVersionIds = [];
        $removedInputStepId = $removedStepVersion->input_step_id === null
            ? null
            : (int) $removedStepVersion->input_step_id;

        foreach ($orderedVersionSteps as $versionStep) {
            $stepVersion = $versionStep->stepVersion;
            $step = $stepVersion?->step;

            if ($stepVersion === null || $step === null) {
                continue;
            }

            if ((int) $step->id === (int) $removedStep->id) {
                continue;
            }

            $desiredInputStepId = (int) $stepVersion->input_step_id === (int) $removedStep->id
                ? $removedInputStepId
                : ($stepVersion->input_step_id === null ? null : (int) $stepVersion->input_step_id);

            $stepVersionId = (int) $stepVersion->id;

            if ($desiredInputStepId !== ($stepVersion->input_step_id === null ? null : (int) $stepVersion->input_step_id)) {
                $newStepVersion = $this->createStepVersionWithUpdatedInput($step, $stepVersion, $desiredInputStepId);

                $changelogEntries[] = sprintf(
                    '- Обновлен источник для шага «%s» из-за удаления шага «%s»',
                    trim((string) ($newStepVersion->name ?? 'Без названия шага')),
                    $removedStepName,
                );

                $stepVersionId = (int) $newStepVersion->id;
            }

            $stepVersionIds[] = $stepVersionId;
        }

        return $stepVersionIds;
    }

    private function createStepVersionWithUpdatedInput(Step $step, StepVersion $stepVersion, ?int $inputStepId): StepVersion
    {
        $nextStepVersionNumber = ((int) $step->versions()->max('version')) + 1;

        $newStepVersion = $step->versions()->create([
            'name' => $stepVersion->name,
            'type' => $stepVersion->type,
            'version' => $nextStepVersionNumber,
            'description' => $stepVersion->description,
            'prompt' => $stepVersion->prompt,
            'settings' => $stepVersion->settings,
            'status' => $stepVersion->status,
            'input_step_id' => $inputStepId,
        ]);

        $step->update(['current_version_id' => $newStepVersion->id]);

        return $newStepVersion;
    }

    private function composeChangelog(?string $existing, string $entry): string
    {
        return collect([$existing, $entry])
            ->filter(fn (?string $value): bool => ! empty(trim((string) $value)))
            ->implode("\n");
    }
}
