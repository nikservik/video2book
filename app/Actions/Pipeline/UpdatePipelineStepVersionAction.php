<?php

namespace App\Actions\Pipeline;

use App\Models\StepVersion;

class UpdatePipelineStepVersionAction
{
    /**
     * @param  array{name:string,type:string,description:?string,prompt:?string,settings:array<string,mixed>,input_step_id:?int}  $payload
     */
    public function handle(StepVersion $stepVersion, array $payload, bool $activateDraft = false): StepVersion
    {
        $attributes = [
            'name' => $payload['name'],
            'type' => $payload['type'],
            'description' => $payload['description'],
            'prompt' => $payload['prompt'],
            'settings' => $payload['settings'],
            'input_step_id' => $payload['input_step_id'],
        ];

        if ($activateDraft && $stepVersion->status === 'draft') {
            $attributes['status'] = 'active';
        }

        $stepVersion->update($attributes);

        return $stepVersion->refresh();
    }
}
