<?php

namespace App\Actions\Project;

use App\Models\Folder;

class RenameFolderAction
{
    /**
     * @param  array<int, int>  $visibleFor
     */
    public function handle(Folder $folder, string $name, bool $hidden = false, array $visibleFor = []): Folder
    {
        $folder->update([
            'name' => trim($name),
            'hidden' => $hidden,
            'visible_for' => $hidden
                ? array_values(array_unique(array_map(static fn (int $userId): int => (int) $userId, $visibleFor)))
                : [],
        ]);

        return $folder->fresh();
    }
}
