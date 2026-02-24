<?php

namespace App\Actions\Project;

use App\Models\Folder;
use App\Models\Project;

class MoveProjectToFolderAction
{
    public function handle(Project $project, Folder $folder): Project
    {
        if ((int) $project->folder_id === (int) $folder->id) {
            return $project;
        }

        $project->update([
            'folder_id' => $folder->id,
        ]);

        return $project->fresh();
    }
}
