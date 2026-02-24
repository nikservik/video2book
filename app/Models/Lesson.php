<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Lesson extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected static $recordEvents = ['created', 'updated', 'deleted'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'name',
        'tag',
        'source_filename',
        'settings',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('lessons')
            ->logOnly([
                'project_id',
                'name',
                'tag',
                'source_filename',
                'settings',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tagRelation(): BelongsTo
    {
        return $this->belongsTo(ProjectTag::class, 'tag', 'slug');
    }

    public function pipelineRuns(): HasMany
    {
        return $this->hasMany(PipelineRun::class);
    }

    public function latestPipelineRun(): HasOne
    {
        return $this->hasOne(PipelineRun::class)->latestOfMany('id');
    }
}
