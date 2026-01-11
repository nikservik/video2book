<?php

namespace Tests\Feature;

use App\Jobs\ProcessPipelineJob;
use App\Models\Lesson;
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
        $project = Project::query()->create(['name' => 'Demo project', 'tags' => 'tag']);
        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson Run',
            'tag' => $tag->slug,
            'settings' => ['quality' => 'high'],
        ]);

        $response = $this->postJson('/api/pipeline-runs', [
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $version->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.lesson.id', $lesson->id)
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
        $project = Project::query()->create(['name' => 'Queued project', 'tags' => 'demo']);
        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson 1',
            'tag' => $tag->slug,
            'settings' => ['mode' => 'auto'],
        ]);
        $lessonB = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson 2',
            'tag' => $tag->slug,
            'settings' => ['mode' => 'auto'],
        ]);

        $service = app(\App\Services\Pipeline\PipelineRunService::class);
        $runA = $service->createRun($lesson, $version, dispatchJob: false);
        $runB = $service->createRun($lessonB, $version, dispatchJob: false);
        $runB->update(['status' => 'running']);

        $response = $this->getJson('/api/pipeline-runs/queue');
        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.steps.0', fn ($value) => ! array_key_exists('result', $value));
    }

    public function test_it_shows_pipeline_run_details(): void
    {
        Queue::fake();

        [$pipeline, $version] = $this->createPipelineWithSteps();
        $tag = ProjectTag::query()->create(['slug' => 'demo', 'description' => null]);
        $project = Project::query()->create(['name' => 'Show project', 'tags' => 'demo']);
        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson show',
            'tag' => $tag->slug,
            'settings' => ['quality' => 'high'],
        ]);

        $run = app(\App\Services\Pipeline\PipelineRunService::class)->createRun($lesson, $version, dispatchJob: false);
        $step = $run->steps()->orderBy('position')->first();
        $step?->update([
            'status' => 'done',
            'result' => 'TRANSCRIPT',
            'input_tokens' => 100,
            'output_tokens' => 200,
        ]);
        $run->update(['status' => 'running']);

        $response = $this->getJson("/api/pipeline-runs/{$run->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $run->id)
            ->assertJsonPath('data.lesson.id', $lesson->id)
            ->assertJsonPath('data.pipeline_version.id', $version->id)
            ->assertJsonPath('data.steps.0.result', 'TRANSCRIPT')
            ->assertJsonPath('data.steps.0.status', 'done');
    }

    public function test_it_streams_pipeline_run_snapshot_with_results(): void
    {
        [$pipeline, $version] = $this->createPipelineWithSteps();
        $tag = ProjectTag::query()->create(['slug' => 'demo', 'description' => null]);
        $project = Project::query()->create(['name' => 'Streaming project', 'tags' => 'demo']);
        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Streaming lesson',
            'tag' => $tag->slug,
            'settings' => ['quality' => 'high'],
        ]);

        $run = app(\App\Services\Pipeline\PipelineRunService::class)->createRun($lesson, $version, dispatchJob: false);
        $step = $run->steps()->orderBy('position')->first();
        $step?->update([
            'status' => 'done',
            'result' => 'FINISHED STEP',
        ]);
        $run->update(['status' => 'done']);

        $response = $this->get("/api/pipeline-runs/{$run->id}/events?once=1");

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

        $payload = $response->streamedContent();

        $this->assertStringContainsString('event: run-snapshot', $payload);
        $this->assertStringContainsString('"result":"FINISHED STEP"', $payload);
        $this->assertStringContainsString('"status":"done"', $payload);
    }

    public function test_it_limits_event_history_per_stream(): void
    {
        [$pipeline, $version] = $this->createPipelineWithSteps();
        $tag = ProjectTag::query()->create(['slug' => 'demo', 'description' => null]);
        $project = Project::query()->create(['name' => 'Event limit project', 'tags' => 'demo']);
        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Event limit lesson',
            'tag' => $tag->slug,
            'settings' => ['quality' => 'high'],
        ]);

        $run = app(\App\Services\Pipeline\PipelineRunService::class)->createRun($lesson, $version, dispatchJob: false);

        $broadcaster = app(\App\Services\Pipeline\PipelineEventBroadcaster::class);

        $eventsToCreate = \App\Services\Pipeline\PipelineEventBroadcaster::STREAM_EVENT_LIMIT + 50;

        for ($i = 0; $i < $eventsToCreate; $i++) {
            $broadcaster->queueRunUpdated($run);
        }

        $count = \App\Models\PipelineQueueEvent::query()
            ->where('stream', \App\Services\Pipeline\PipelineEventBroadcaster::QUEUE_STREAM)
            ->count();

        $this->assertLessThanOrEqual(\App\Services\Pipeline\PipelineEventBroadcaster::STREAM_EVENT_LIMIT, $count);
    }

    public function test_it_restarts_run_from_specific_step(): void
    {
        Queue::fake();

        [$pipeline, $version] = $this->createPipelineWithSteps();
        $tag = ProjectTag::query()->create(['slug' => 'demo', 'description' => null]);
        $project = Project::query()->create(['name' => 'Restarted project', 'tags' => 'manual']);
        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Restarted lesson',
            'tag' => $tag->slug,
            'settings' => ['mode' => 'manual'],
        ]);

        $run = app(\App\Services\Pipeline\PipelineRunService::class)->createRun($lesson, $version, dispatchJob: false);

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
