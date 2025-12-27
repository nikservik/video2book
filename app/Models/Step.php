<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Step extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pipeline_id',
        'current_version_id',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(StepVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(StepVersion::class);
    }
}
