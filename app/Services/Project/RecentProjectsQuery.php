<?php

namespace App\Services\Project;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class RecentProjectsQuery
{
    public function get(int $limit = 5, ?User $viewer = null): Collection
    {
        $viewer ??= auth()->user();

        return Project::query()
            ->whereHas('folder', fn (Builder $query): Builder => $query->visibleTo($viewer))
            ->with('folder:id,name')
            ->withCount('lessons')
            ->latest('updated_at')
            ->limit($limit)
            ->get(['id', 'folder_id', 'name', 'settings', 'updated_at']);
    }
}
