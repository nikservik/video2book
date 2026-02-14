<?php

namespace App\Actions\Project;

use App\Models\Project;

class UpdateProjectNameAction
{
    public function handle(Project $project, string $name): void
    {
        $project->update([
            'name' => $name,
        ]);
    }
}
