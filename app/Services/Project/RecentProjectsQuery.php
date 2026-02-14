<?php

namespace App\Services\Project;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

class RecentProjectsQuery
{
    public function get(int $limit = 5): Collection
    {
        return Project::query()
            ->latest('updated_at')
            ->limit($limit)
            ->get(['id', 'name', 'updated_at']);
    }
}
