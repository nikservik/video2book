<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StepVersion extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'step_id',
        'input_step_id',
        'name',
        'type',
        'version',
        'description',
        'prompt',
        'settings',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'version' => 'integer',
        'settings' => 'array',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(Step::class);
    }

    public function inputStep(): BelongsTo
    {
        return $this->belongsTo(Step::class, 'input_step_id');
    }

    public function inputStepCurrentVersion(): ?self
    {
        return $this->inputStep?->currentVersion;
    }

    public function pipelineVersionSteps(): HasMany
    {
        return $this->hasMany(PipelineVersionStep::class);
    }
}
