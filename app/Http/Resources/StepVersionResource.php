<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StepVersionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'step_id' => $this->step_id,
            'input_step_id' => $this->input_step_id,
            'name' => $this->name,
            'version' => $this->version,
            'type' => $this->type,
            'description' => $this->description,
            'prompt' => $this->prompt,
            'settings' => $this->settings,
            'status' => $this->status,
            'input_step' => $this->when($this->relationLoaded('inputStep'), function () {
                $inputStep = $this->inputStep;

                if ($inputStep === null) {
                    return null;
                }

                return [
                    'id' => $inputStep->id,
                    'name' => $inputStep->currentVersion?->name,
                    'current_version_id' => $inputStep->current_version_id,
                ];
            }),
        ];
    }
}
