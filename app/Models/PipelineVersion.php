<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineVersion extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pipeline_id',
        'version',
        'title',
        'description',
        'changelog',
        'created_by',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'version' => 'integer',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versionSteps(): HasMany
    {
        return $this->hasMany(PipelineVersionStep::class);
    }

    public function stepVersions(): BelongsToMany
    {
        return $this->belongsToMany(StepVersion::class, 'pipeline_version_steps')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('pipeline_version_steps.position');
    }
}
