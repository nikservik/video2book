<?php

namespace Tests\Unit\Pipeline;

use App\Actions\Pipeline\PausePipelineRunAction;
use App\Actions\Pipeline\StartPipelineRunAction;
use App\Actions\Pipeline\StopPipelineRunAction;
use App\Jobs\ProcessPipelineJob;
use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Project;
use App\Models\ProjectTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PipelineRunControlActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pause_action_moves_pending_steps_to_paused_and_keeps_running_step(): void
    {
        [$run, $runningStep, $pendingStep] = $this->createRunForControls();

        app(PausePipelineRunAction::class)->handle($run);

        $this->assertSame('running', $run->fresh()->status);
        $this->assertSame('running', $runningStep->fresh()->status);
        $this->assertSame('paused', $pendingStep->fresh()->status);
        $this->assertNull(data_get($run->fresh()->state, 'stop_requested'));
    }

    public function test_stop_action_pauses_all_unfinished_steps_and_marks_run_paused(): void
    {
        [$run, $runningStep, $pendingStep] = $this->createRunForControls();

        app(StopPipelineRunAction::class)->handle($run);

        $this->assertSame('paused', $run->fresh()->status);
        $this->assertSame('paused', $runningStep->fresh()->status);
        $this->assertSame('paused', $pendingStep->fresh()->status);
        $this->assertTrue((bool) data_get($run->fresh()->state, 'stop_requested'));
    }

    public function test_start_action_resumes_paused_steps_and_dispatches_processing(): void
    {
        Queue::fake();

        [$run, $runningStep, $pendingStep] = $this->createRunForControls();

        app(StopPipelineRunAction::class)->handle($run);
        app(StartPipelineRunAction::class)->handle($run->fresh());

        $this->assertSame('queued', $run->fresh()->status);
        $this->assertSame('pending', $runningStep->fresh()->status);
        $this->assertSame('pending', $pendingStep->fresh()->status);
        $this->assertNull(data_get($run->fresh()->state, 'stop_requested'));

        Queue::assertPushedOn(ProcessPipelineJob::QUEUE, ProcessPipelineJob::class, function (ProcessPipelineJob $job) use ($run): bool {
            return $job->pipelineRunId === $run->id;
        });
    }

    /**
     * @return array{0: PipelineRun, 1: PipelineRunStep, 2: PipelineRunStep}
     */
    private function createRunForControls(): array
    {
        $tag = ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Controls project',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Controls lesson',
            'tag' => $tag->slug,
            'source_filename' => null,
            'settings' => [],
        ]);

        [$pipeline, $version] = $this->createPipelineWithSteps();

        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $version->id,
            'status' => 'running',
            'state' => [],
        ]);

        $stepVersionIds = $pipeline->steps()
            ->with('currentVersion')
            ->get()
            ->pluck('currentVersion.id')
            ->values();

        PipelineRunStep::query()->create([
            'pipeline_run_id' => $run->id,
            'step_version_id' => $stepVersionIds[0],
            'position' => 1,
            'status' => 'done',
            'result' => 'done',
            'error' => null,
            'start_time' => null,
            'end_time' => null,
            'input_tokens' => 1,
            'output_tokens' => 1,
            'cost' => 0.001,
        ]);

        $runningStep = PipelineRunStep::query()->create([
            'pipeline_run_id' => $run->id,
            'step_version_id' => $stepVersionIds[1],
            'position' => 2,
            'status' => 'running',
            'result' => null,
            'error' => null,
            'start_time' => now(),
            'end_time' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'cost' => null,
        ]);

        $pendingStep = PipelineRunStep::query()->create([
            'pipeline_run_id' => $run->id,
            'step_version_id' => $stepVersionIds[2],
            'position' => 3,
            'status' => 'pending',
            'result' => null,
            'error' => null,
            'start_time' => null,
            'end_time' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'cost' => null,
        ]);

        return [$run, $runningStep, $pendingStep];
    }

    /**
     * @return array{Pipeline, PipelineVersion}
     */
    private function createPipelineWithSteps(): array
    {
        $pipeline = Pipeline::query()->create();

        $version = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Controls pipeline',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);

        $pipeline->update(['current_version_id' => $version->id]);

        $this->createStep($pipeline, $version, 1, 'transcribe');
        $this->createStep($pipeline, $version, 2, 'text');
        $this->createStep($pipeline, $version, 3, 'text');

        return [$pipeline, $version];
    }

    private function createStep(Pipeline $pipeline, PipelineVersion $version, int $position, string $type): void
    {
        $step = $pipeline->steps()->create();

        $stepVersion = $step->versions()->create([
            'name' => 'Step '.$position,
            'type' => $type,
            'version' => 1,
            'description' => null,
            'prompt' => 'Prompt '.$position,
            'settings' => [
                'provider' => 'openai',
                'model' => $type === 'transcribe' ? 'whisper-1' : 'gpt-5-mini',
                'temperature' => 0.2,
            ],
            'status' => 'active',
        ]);

        $step->update(['current_version_id' => $stepVersion->id]);

        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $version->id,
            'step_version_id' => $stepVersion->id,
            'position' => $position,
        ]);
    }
}
