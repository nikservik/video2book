<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Project extends Model
{
    use HasFactory;
    use LogsActivity {
        shouldLogEvent as protected shouldLogActivityEvent;
    }
    use SoftDeletes;

    protected static $recordEvents = ['created', 'updated', 'deleted'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folder_id',
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('projects')
            ->logOnly([
                'folder_id',
                'name',
                'tags',
                'default_pipeline_version_id',
                'referer',
                'settings',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function shouldLogEvent(string $eventName): bool
    {
        $causer = auth()->user();

        if (! $causer instanceof User) {
            return false;
        }

        return $this->shouldLogActivityEvent($eventName);
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            if ($project->folder_id !== null) {
                return;
            }

            $project->folder_id = (int) Folder::query()
                ->firstOrCreate(['name' => 'Проекты'])
                ->id;
        });
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function defaultPipelineVersion(): BelongsTo
    {
        return $this->belongsTo(PipelineVersion::class, 'default_pipeline_version_id');
    }
}
