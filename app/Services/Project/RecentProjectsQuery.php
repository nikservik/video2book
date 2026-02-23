<?php

namespace App\Services\Project;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

class RecentProjectsQuery
{
    public function get(int $limit = 6): Collection
    {
        return Project::query()
            ->withCount('lessons')
            ->latest('updated_at')
            ->limit($limit)
            ->get(['id', 'name', 'updated_at']);
    }
}
