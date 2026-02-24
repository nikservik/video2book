<?php

namespace App\Services\Project;

use App\Models\Folder;
use Illuminate\Support\Collection;

class ProjectFoldersQuery
{
    /**
     * @return Collection<int, Folder>
     */
    public function get(): Collection
    {
        return Folder::query()
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
