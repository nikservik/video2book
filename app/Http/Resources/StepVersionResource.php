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
            'version' => $this->version,
            'type' => $this->type,
            'description' => $this->description,
            'prompt' => $this->prompt,
            'settings' => $this->settings,
            'status' => $this->status,
        ];
    }
}
