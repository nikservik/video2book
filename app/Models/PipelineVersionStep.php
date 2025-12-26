<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineVersionStep extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pipeline_version_id',
        'step_version_id',
        'position',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'position' => 'integer',
    ];

    public function pipelineVersion(): BelongsTo
    {
        return $this->belongsTo(PipelineVersion::class);
    }

    public function stepVersion(): BelongsTo
    {
        return $this->belongsTo(StepVersion::class);
    }
}
