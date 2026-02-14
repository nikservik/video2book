<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineRun extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lesson_id',
        'pipeline_version_id',
        'status',
        'state',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'state' => 'array',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function pipelineVersion(): BelongsTo
    {
        return $this->belongsTo(PipelineVersion::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(PipelineRunStep::class)
            ->orderBy('position')
            ->orderBy('id');
    }
}
