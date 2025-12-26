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

    public function pipelineVersionSteps(): HasMany
    {
        return $this->hasMany(PipelineVersionStep::class);
    }
}
