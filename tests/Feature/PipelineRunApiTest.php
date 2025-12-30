<?php

namespace Tests\Feature;

use App\Jobs\ProcessPipelineJob;
use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Project;
use App\Models\ProjectTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PipelineRunApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_pipeline_run_and_dispatches_job(): void
    {
        Queue::fake();

        [$pipeline, $version] = $this->createPipelineWithSteps();

        $tag = ProjectTag::query()->create(['slug' => 'demo', 'description' => null]);
        $project = Project::query()->create([
            'name' => 'Demo project',
            'tag' => $tag->slug,
            'settings' => ['quality' => 'high'],
        ]);

        $response = $this->postJson('/api/pipeline-runs', [
            'project_id' => $project->id,
            'pipeline_version_id' => $version->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonPath('data.pipeline_version.id', $version->id)
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonCount(2, 'data.steps');

        $runId = $response->json('data.id');

        Queue::assertPushedOn(ProcessPipelineJob::QUEUE, ProcessPipelineJob::class, function (ProcessPipelineJob $job) use ($runId) {
            return $job->pipelineRunId === $runId;
        });
    }

    public function test_it_returns_queue_state(): void
    {
        Queue::fake();

        [$pipeline, $version] = $this->createPipelineWithSteps();
        $tag = ProjectTag::query()->create(['slug' => 'demo', 'description' => null]);
        $project = Project::query()->create([
            'name' => 'Queued project',
            'tag' => $tag->slug,
            'settings' => ['mode' => 'auto'],
        ]);

        $service = app(\App\Services\Pipeline\PipelineRunService::class);
        $runA = $service->createRun($project, $version, dispatchJob: false);
        $runB = $service->createRun($project, $version, dispatchJob: false);
        $runB->update(['status' => 'running']);

        $response = $this->getJson('/api/pipeline-runs/queue');
        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.steps.0', fn ($value) => ! array_key_exists('result', $value));
    }

    public function test_it_restarts_run_from_specific_step(): void
    {
        Queue::fake();

        [$pipeline, $version] = $this->createPipelineWithSteps();
        $tag = ProjectTag::query()->create(['slug' => 'demo', 'description' => null]);
        $project = Project::query()->create([
            'name' => 'Restarted project',
            'tag' => $tag->slug,
            'settings' => ['mode' => 'manual'],
        ]);

        $run = app(\App\Services\Pipeline\PipelineRunService::class)->createRun($project, $version, dispatchJob: false);

        $steps = $run->steps()->orderBy('position')->get();
        $firstStep = $steps[0];
        $secondStep = $steps[1];

        $run->update(['status' => 'running']);
        $firstStep->update([
            'status' => 'done',
            'result' => 'FIRST RESULT',
            'input_tokens' => 10,
            'output_tokens' => 20,
            'cost' => 0.01,
        ]);
        $secondStep->update([
            'status' => 'running',
            'result' => 'SECOND RESULT',
            'error' => 'temporary',
        ]);

        $response = $this->postJson("/api/pipeline-runs/{$run->id}/restart", [
            'step_id' => $secondStep->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $run->id)
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.steps.0.status', 'done')
            ->assertJsonPath('data.steps.1.status', 'pending')
            ->assertJsonPath('data.steps.1.result', null);

        $secondStep->refresh();
        $this->assertNull($secondStep->result);
        $this->assertNull($secondStep->error);
        $this->assertNull($secondStep->start_time);
        $this->assertEquals('pending', $secondStep->status);

        Queue::assertPushedOn(ProcessPipelineJob::QUEUE, ProcessPipelineJob::class, function (ProcessPipelineJob $job) use ($run) {
            return $job->pipelineRunId === $run->id;
        });
    }

    /**
     * @return array{0: Pipeline, 1: PipelineVersion}
     */
    private function createPipelineWithSteps(): array
    {
        $pipeline = Pipeline::query()->create();
        $version = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Pipeline title',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);
        $pipeline->update(['current_version_id' => $version->id]);

        $this->createStepForPipeline($pipeline, $version, 1, 'transcribe');
        $this->createStepForPipeline($pipeline, $version, 2, 'text');

        return [$pipeline, $version];
    }

    private function createStepForPipeline(Pipeline $pipeline, PipelineVersion $version, int $position, string $type): void
    {
        $step = $pipeline->steps()->create();
        $stepVersion = $step->versions()->create([
            'name' => 'Step '.$position,
            'type' => $type,
            'version' => 1,
            'description' => null,
            'prompt' => $type === 'transcribe' ? 'Transcribe audio' : 'Process text',
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
