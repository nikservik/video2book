<?php

namespace App\Services\User;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginatedUsersQuery
{
    public function getVisibleFor(User $viewer, int $perPage = 9): LengthAwarePaginator
    {
        return User::query()
            ->when(
                $viewer->isSuperAdmin(),
                fn ($query) => $query->where(function ($innerQuery) use ($viewer): void {
                    $innerQuery
                        ->where('access_level', '<', User::ACCESS_LEVEL_SUPERADMIN)
                        ->orWhere('id', $viewer->id);
                }),
                fn ($query) => $query->where('access_level', '<', User::ACCESS_LEVEL_SUPERADMIN)
            )
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
