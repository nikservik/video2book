<?php

namespace Tests\Unit\Project;

use App\Actions\Project\UpdateProjectLessonNameAction;
use App\Models\Lesson;
use App\Models\Project;
use App\Models\ProjectTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateProjectLessonNameActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_lesson_name_in_project_scope(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Старое имя',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        app(UpdateProjectLessonNameAction::class)->handle($project, $lesson->id, 'Новое имя');

        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'project_id' => $project->id,
            'name' => 'Новое имя',
        ]);
    }
}
