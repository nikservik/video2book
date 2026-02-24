<?php

namespace App\Services\Project;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Collection;

class ProjectFoldersQuery
{
    /**
     * @return Collection<int, Folder>
     */
    public function get(?User $viewer = null): Collection
    {
        return Folder::query()
            ->visibleTo($viewer)
            ->withCount('projects')
            ->with([
                'projects' => function ($query): void {
                    $query
                        ->withCount('lessons')
                        ->orderByDesc('updated_at');
                },
            ])
            ->orderBy('name')
            ->get();
    }
}
