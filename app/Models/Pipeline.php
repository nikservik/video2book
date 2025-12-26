<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pipeline extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'current_version_id',
    ];

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(PipelineVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PipelineVersion::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(Step::class);
    }
}
