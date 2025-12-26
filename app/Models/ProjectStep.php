<?php

namespace App\Models;

use App\Models\Project;
use App\Models\StepVersion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectStep extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'step_version_id',
        'start_time',
        'end_time',
        'error',
        'result',
        'input_tokens',
        'output_tokens',
        'cost',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'cost' => 'decimal:4',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stepVersion(): BelongsTo
    {
        return $this->belongsTo(StepVersion::class);
    }
}
