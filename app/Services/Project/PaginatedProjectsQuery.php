<?php

namespace App\Services\Project;

use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginatedProjectsQuery
{
    public function get(int $perPage = 9): LengthAwarePaginator
    {
        return Project::query()
            ->withCount('lessons')
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
