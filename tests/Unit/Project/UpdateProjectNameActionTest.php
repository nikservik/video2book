<?php

namespace Tests\Unit\Project;

use App\Actions\Project\UpdateProjectNameAction;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateProjectNameActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_project_fields(): void
    {
        $pipeline = \App\Models\Pipeline::query()->create();
        $pipelineVersion = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Базовый пайплайн',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);

        $project = Project::query()->create([
            'name' => 'Исходное название',
            'tags' => null,
            'referer' => null,
            'default_pipeline_version_id' => null,
        ]);

        app(UpdateProjectNameAction::class)->handle(
            $project,
            'Обновленное название',
            'https://www.somesite.com/',
            $pipelineVersion->id,
        );

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Обновленное название',
            'referer' => 'https://www.somesite.com/',
            'default_pipeline_version_id' => $pipelineVersion->id,
        ]);
    }
}
