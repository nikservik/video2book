<?php

namespace App\Services\Pipeline;

use App\Models\Pipeline;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginatedPipelinesQuery
{
    public function get(int $perPage = 9): LengthAwarePaginator
    {
        return Pipeline::query()
            ->with([
                'currentVersion:id,pipeline_id,title,version,description',
            ])
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
