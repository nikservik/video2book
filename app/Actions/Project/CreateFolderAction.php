<?php

namespace App\Actions\Project;

use App\Models\Folder;

class CreateFolderAction
{
    /**
     * @param  array<int, int>  $visibleFor
     */
    public function handle(string $name, bool $hidden = false, array $visibleFor = []): Folder
    {
        return Folder::query()->create([
            'name' => trim($name),
            'hidden' => $hidden,
            'visible_for' => $hidden
                ? array_values(array_unique(array_map(static fn (int $userId): int => (int) $userId, $visibleFor)))
                : [],
        ]);
    }
}
