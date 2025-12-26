<?php

namespace App\Models;

use App\Models\PipelineVersion;
use App\Models\ProjectStep;
use App\Models\ProjectTag;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'tag',
        'source_filename',
        'pipeline_version_id',
        'settings',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
    ];

    public function pipelineVersion(): BelongsTo
    {
        return $this->belongsTo(PipelineVersion::class);
    }

    public function tagRelation(): BelongsTo
    {
        return $this->belongsTo(ProjectTag::class, 'tag', 'slug');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProjectStep::class);
    }
}
