<?php

namespace App\Models;

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
        'tags',
        'default_pipeline_version_id',
        'referer',
        'settings',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
    ];

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    public function defaultPipelineVersion(): BelongsTo
    {
        return $this->belongsTo(PipelineVersion::class, 'default_pipeline_version_id');
    }
}
