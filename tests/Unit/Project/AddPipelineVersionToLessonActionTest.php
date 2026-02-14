<?php

namespace Tests\Unit\Project;

use App\Actions\Project\AddPipelineVersionToLessonAction;
use App\Jobs\ProcessPipelineJob;
use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Project;
use App\Models\ProjectTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AddPipelineVersionToLessonActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_pipeline_version_to_lesson(): void
    {
        Queue::fake();

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
            'name' => 'Урок',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        $version = $this->createPipelineVersionWithStep();

        app(AddPipelineVersionToLessonAction::class)->handle($project, $lesson->id, $version->id);

        $this->assertDatabaseHas('pipeline_runs', [
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $version->id,
            'status' => 'queued',
        ]);

        Queue::assertPushed(ProcessPipelineJob::class);
    }

    public function test_it_throws_validation_exception_when_version_is_already_added(): void
    {
        $this->expectException(ValidationException::class);

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
            'name' => 'Урок',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        $version = $this->createPipelineVersionWithStep();

        PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $version->id,
            'status' => 'done',
            'state' => [],
        ]);

        app(AddPipelineVersionToLessonAction::class)->handle($project, $lesson->id, $version->id);
    }

    private function createPipelineVersionWithStep(): PipelineVersion
    {
        $pipeline = Pipeline::query()->create();
        $version = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Пайплайн',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);

        $step = $pipeline->steps()->create();
        $stepVersion = $step->versions()->create([
            'name' => 'Шаг',
            'type' => 'text',
            'version' => 1,
            'description' => null,
            'prompt' => 'Prompt',
            'settings' => [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
            ],
            'status' => 'active',
        ]);
        $step->update(['current_version_id' => $stepVersion->id]);

        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $version->id,
            'step_version_id' => $stepVersion->id,
            'position' => 1,
        ]);

        return $version;
    }
}
