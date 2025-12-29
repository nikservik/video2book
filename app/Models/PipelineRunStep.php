<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineRunStep extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pipeline_run_id',
        'step_version_id',
        'position',
        'start_time',
        'end_time',
        'error',
        'result',
        'status',
        'input_tokens',
        'output_tokens',
        'cost',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'position' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'cost' => 'decimal:4',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
    ];

    public function pipelineRun(): BelongsTo
    {
        return $this->belongsTo(PipelineRun::class);
    }

    public function stepVersion(): BelongsTo
    {
        return $this->belongsTo(StepVersion::class);
    }
}
