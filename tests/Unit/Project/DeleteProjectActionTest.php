<?php

namespace Tests\Unit\Project;

use App\Actions\Project\DeleteProjectAction;
use App\Models\Lesson;
use App\Models\Project;
use App\Models\ProjectTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteProjectActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_project_with_related_lessons(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект для action',
            'tags' => null,
        ]);

        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок для удаления action',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        app(DeleteProjectAction::class)->handle($project);

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('lessons', ['project_id' => $project->id]);
    }
}
