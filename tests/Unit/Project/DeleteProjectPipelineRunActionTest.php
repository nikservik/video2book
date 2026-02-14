<?php

namespace Tests\Unit\Project;

use App\Actions\Project\DeleteProjectPipelineRunAction;
use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Models\Project;
use App\Models\ProjectTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteProjectPipelineRunActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_pipeline_run_in_project_scope(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект с прогоном',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        $pipeline = Pipeline::query()->create();
        $version = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Пайплайн',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);

        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $version->id,
            'status' => 'queued',
            'state' => [],
        ]);

        app(DeleteProjectPipelineRunAction::class)->handle($project, $run->id);

        $this->assertDatabaseMissing('pipeline_runs', ['id' => $run->id]);
    }
}
