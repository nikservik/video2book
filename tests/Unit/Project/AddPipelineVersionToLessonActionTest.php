<?php

namespace Tests\Unit\Project;

use App\Actions\Project\AddPipelineVersionToLessonAction;
use App\Jobs\ProcessPipelineJob;
use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Project;
use App\Models\ProjectTag;
use App\Models\StepVersion;
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

        [$project, $lesson] = $this->createProjectLesson();

        $version = $this->createPipelineVersionWithStep();

        app(AddPipelineVersionToLessonAction::class)->handle($project, $lesson->id, $version->id);

        $this->assertDatabaseHas('pipeline_runs', [
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $version->id,
            'status' => 'queued',
        ]);

        Queue::assertPushed(ProcessPipelineJob::class);
    }

    public function test_it_reuses_unchanged_prefix_steps_until_first_changed_step(): void
    {
        Queue::fake();

        [$project, $lesson] = $this->createProjectLesson();
        [$pipeline, $sourceVersion, $sourceStepVersions] = $this->createPipelineVersionWithThreeSteps();

        $this->createCompletedRun($lesson, $sourceVersion, $sourceStepVersions);

        [$targetVersion, $changedThirdStepVersion] = $this->createVersionWithChangedThirdStep(
            $pipeline,
            $sourceVersion,
            $sourceStepVersions,
        );

        app(AddPipelineVersionToLessonAction::class)->handle($project, $lesson->id, $targetVersion->id);

        $newRun = PipelineRun::query()
            ->where('lesson_id', $lesson->id)
            ->where('pipeline_version_id', $targetVersion->id)
            ->with('steps')
            ->firstOrFail();

        $this->assertSame('queued', $newRun->status);

        $steps = $newRun->steps
            ->sortBy('position')
            ->values();

        $this->assertSame('done', $steps[0]->status);
        $this->assertSame('OLD-RESULT-1', $steps[0]->result);
        $this->assertSame(0, $steps[0]->input_tokens);
        $this->assertSame(0, $steps[0]->output_tokens);
        $this->assertSame('0.0000', (string) $steps[0]->cost);
        $this->assertNull($steps[0]->start_time);
        $this->assertNull($steps[0]->end_time);
        $this->assertSame($sourceStepVersions[0]->id, $steps[0]->step_version_id);

        $this->assertSame('done', $steps[1]->status);
        $this->assertSame('OLD-RESULT-2', $steps[1]->result);
        $this->assertSame(0, $steps[1]->input_tokens);
        $this->assertSame(0, $steps[1]->output_tokens);
        $this->assertSame('0.0000', (string) $steps[1]->cost);
        $this->assertNull($steps[1]->start_time);
        $this->assertNull($steps[1]->end_time);
        $this->assertSame($sourceStepVersions[1]->id, $steps[1]->step_version_id);

        $this->assertSame('pending', $steps[2]->status);
        $this->assertNull($steps[2]->result);
        $this->assertNull($steps[2]->input_tokens);
        $this->assertNull($steps[2]->output_tokens);
        $this->assertNull($steps[2]->cost);
        $this->assertSame($changedThirdStepVersion->id, $steps[2]->step_version_id);

        Queue::assertPushedOn(ProcessPipelineJob::QUEUE, ProcessPipelineJob::class, function (ProcessPipelineJob $job) use ($newRun): bool {
            return $job->pipelineRunId === $newRun->id;
        });
    }

    public function test_it_throws_validation_exception_when_version_is_already_added(): void
    {
        $this->expectException(ValidationException::class);

        [$project, $lesson] = $this->createProjectLesson();

        $version = $this->createPipelineVersionWithStep();

        PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $version->id,
            'status' => 'done',
            'state' => [],
        ]);

        app(AddPipelineVersionToLessonAction::class)->handle($project, $lesson->id, $version->id);
    }

    /**
     * @return array{0: Project, 1: Lesson}
     */
    private function createProjectLesson(): array
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
            'name' => 'Урок',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        return [$project, $lesson];
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

    /**
     * @return array{0: Pipeline, 1: PipelineVersion, 2: array<int, StepVersion>}
     */
    private function createPipelineVersionWithThreeSteps(): array
    {
        $pipeline = Pipeline::query()->create();
        $version = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Пайплайн',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);
        $pipeline->update(['current_version_id' => $version->id]);

        $stepVersions = [];

        for ($position = 1; $position <= 3; $position++) {
            $step = $pipeline->steps()->create();
            $stepVersion = $step->versions()->create([
                'name' => 'Шаг '.$position,
                'type' => 'text',
                'version' => 1,
                'description' => null,
                'prompt' => 'Prompt '.$position,
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
                'position' => $position,
            ]);

            $stepVersions[] = $stepVersion;
        }

        return [$pipeline, $version, $stepVersions];
    }

    private function createCompletedRun(Lesson $lesson, PipelineVersion $version, array $stepVersions): PipelineRun
    {
        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $version->id,
            'status' => 'done',
            'state' => [],
        ]);

        foreach ($stepVersions as $index => $stepVersion) {
            $position = $index + 1;

            PipelineRunStep::query()->create([
                'pipeline_run_id' => $run->id,
                'step_version_id' => $stepVersion->id,
                'position' => $position,
                'status' => 'done',
                'result' => 'OLD-RESULT-'.$position,
                'error' => null,
                'start_time' => now()->subMinutes(10 - $position),
                'end_time' => now()->subMinutes(9 - $position),
                'input_tokens' => 100 + $position,
                'output_tokens' => 200 + $position,
                'cost' => '0.0'.$position,
            ]);
        }

        return $run;
    }

    /**
     * @param  array<int, StepVersion>  $sourceStepVersions
     * @return array{0: PipelineVersion, 1: StepVersion}
     */
    private function createVersionWithChangedThirdStep(
        Pipeline $pipeline,
        PipelineVersion $sourceVersion,
        array $sourceStepVersions,
    ): array {
        $thirdStep = $sourceStepVersions[2]->step;

        $changedThirdStepVersion = $thirdStep->versions()->create([
            'name' => $sourceStepVersions[2]->name,
            'type' => $sourceStepVersions[2]->type,
            'version' => 2,
            'description' => $sourceStepVersions[2]->description,
            'prompt' => 'Updated prompt',
            'settings' => $sourceStepVersions[2]->settings,
            'status' => $sourceStepVersions[2]->status,
            'input_step_id' => $sourceStepVersions[2]->input_step_id,
        ]);
        $thirdStep->update(['current_version_id' => $changedThirdStepVersion->id]);

        $targetVersion = $pipeline->versions()->create([
            'version' => ((int) $sourceVersion->version) + 1,
            'title' => $sourceVersion->title,
            'description' => $sourceVersion->description,
            'changelog' => 'Changed step 3',
            'created_by' => $sourceVersion->created_by,
            'status' => $sourceVersion->status,
        ]);

        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $targetVersion->id,
            'step_version_id' => $sourceStepVersions[0]->id,
            'position' => 1,
        ]);
        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $targetVersion->id,
            'step_version_id' => $sourceStepVersions[1]->id,
            'position' => 2,
        ]);
        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $targetVersion->id,
            'step_version_id' => $changedThirdStepVersion->id,
            'position' => 3,
        ]);

        return [$targetVersion, $changedThirdStepVersion];
    }
}
