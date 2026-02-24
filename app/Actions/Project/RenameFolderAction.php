<?php

namespace App\Actions\Project;

use App\Models\Folder;

class RenameFolderAction
{
    public function handle(Folder $folder, string $name): Folder
    {
        $folder->update([
            'name' => trim($name),
        ]);

        return $folder->fresh();
    }
}
