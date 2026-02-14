<?php

namespace Tests\Unit\Project;

use App\Actions\Project\UpdateProjectNameAction;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateProjectNameActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_project_name(): void
    {
        $project = Project::query()->create([
            'name' => 'Исходное название',
            'tags' => null,
        ]);

        app(UpdateProjectNameAction::class)->handle($project, 'Обновленное название');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Обновленное название',
        ]);
    }
}
