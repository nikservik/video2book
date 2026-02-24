<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folder extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'hidden',
        'visible_for',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'hidden' => 'boolean',
        'visible_for' => 'array',
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user instanceof User && $user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $folderQuery) use ($user): void {
            $folderQuery->where('hidden', false);

            if (! $user instanceof User) {
                return;
            }

            $folderQuery->orWhere(function (Builder $hiddenQuery) use ($user): void {
                $hiddenQuery
                    ->where('hidden', true)
                    ->whereJsonContains('visible_for', (int) $user->id);
            });
        });
    }
}
