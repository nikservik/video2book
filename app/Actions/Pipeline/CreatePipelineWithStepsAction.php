<?php

namespace App\Actions\Pipeline;

use App\Models\Pipeline;
use App\Models\PipelineVersionStep;
use Illuminate\Support\Facades\DB;

class CreatePipelineWithStepsAction
{
    /**
     * @param  array<int, string>  $stepNames
     */
    public function handle(string $title, ?string $description, array $stepNames): Pipeline
    {
        return DB::transaction(function () use ($title, $description, $stepNames): Pipeline {
            $pipeline = Pipeline::query()->create();

            $version = $pipeline->versions()->create([
                'version' => 1,
                'title' => trim($title),
                'description' => $description,
                'changelog' => null,
                'created_by' => null,
                'status' => 'archived',
            ]);

            $pipeline->update(['current_version_id' => $version->id]);

            $previousStep = null;

            foreach (array_values($stepNames) as $index => $name) {
                $step = $pipeline->steps()->create();

                $stepVersion = $step->versions()->create([
                    'name' => trim($name),
                    'type' => $index === 0 ? 'transcribe' : 'text',
                    'version' => 1,
                    'description' => null,
                    'prompt' => null,
                    'settings' => [],
                    'status' => 'draft',
                    'input_step_id' => $previousStep?->id,
                ]);

                $step->update(['current_version_id' => $stepVersion->id]);

                PipelineVersionStep::query()->create([
                    'pipeline_version_id' => $version->id,
                    'step_version_id' => $stepVersion->id,
                    'position' => $index + 1,
                ]);

                $previousStep = $step;
            }

            return $pipeline->fresh();
        });
    }
}
