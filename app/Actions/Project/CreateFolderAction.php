<?php

namespace App\Actions\Project;

use App\Models\Folder;

class CreateFolderAction
{
    public function handle(string $name): Folder
    {
        return Folder::query()->create([
            'name' => trim($name),
        ]);
    }
}
