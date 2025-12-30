<?php

namespace Tests\Feature;

use App\Jobs\ProcessPipelineJob;
use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineRunStep;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Project;
use App\Models\ProjectTag;
use App\Services\Pipeline\Contracts\PipelineStepExecutor;
use App\Services\Pipeline\PipelineRunProcessingService;
use App\Services\Pipeline\PipelineRunService;
use App\Services\Pipeline\PipelineStepResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PipelineRunProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_processes_steps_sequentially(): void
    {
        Queue::fake();

        [$pipeline, $version] = $this->createPipelineWithSteps();
        $tag = ProjectTag::query()->create(['slug' => 'demo', 'description' => null]);
        $project = Project::query()->create(['name' => 'Processing project', 'tags' => 'demo']);
        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Processing lesson',
            'tag' => $tag->slug,
            'settings' => ['quality' => 'medium'],
        ]);

        $run = app(PipelineRunService::class)->createRun($lesson, $version, dispatchJob: false);

        $fakeExecutor = new class implements PipelineStepExecutor
        {
            public array $history = [];

            public function execute(\App\Models\PipelineRun $run, PipelineRunStep $step, ?string $input): PipelineStepResult
            {
                $this->history[] = [$step->stepVersion?->type, $input];

                $output = match ($step->stepVersion?->type) {
                    'transcribe' => 'TRANSCRIBED::'.$run->id,
                    default => 'RESULT::'.($input ?? 'empty'),
                };

                return new PipelineStepResult($output, inputTokens: $input ? strlen($input) : null, outputTokens: strlen($output));
            }
        };

        $this->app->instance(PipelineStepExecutor::class, $fakeExecutor);

        $service = app(PipelineRunProcessingService::class);

        $hasMore = $service->handle($run->id);
        $this->assertTrue($hasMore);

        $run->refresh();
        $this->assertEquals('running', $run->status);
        $this->assertEquals('done', $run->steps()->where('position', 1)->first()?->status);
        $this->assertEquals('TRANSCRIBED::'.$run->id, $run->steps()->where('position', 1)->first()?->result);

        $hasMore = $service->handle($run->id);
        $this->assertFalse($hasMore);

        $run->refresh();
        $this->assertEquals('done', $run->status);
        $this->assertEquals('done', $run->steps()->where('position', 2)->first()?->status);
        $this->assertEquals('RESULT::TRANSCRIBED::'.$run->id, $run->steps()->where('position', 2)->first()?->result);
    }

    public function test_job_requeues_when_pending_steps_exist(): void
    {
        Queue::fake();

        [$pipeline, $version] = $this->createPipelineWithSteps();
        $tag = ProjectTag::query()->create(['slug' => 'demo', 'description' => null]);
        $project = Project::query()->create(['name' => 'Job project', 'tags' => 'x']);
        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Job lesson',
            'tag' => $tag->slug,
            'settings' => ['quality' => 'high'],
        ]);

        $run = app(PipelineRunService::class)->createRun($lesson, $version, dispatchJob: false);

        $fakeExecutor = new class implements PipelineStepExecutor
        {
            public bool $firstStepHandled = false;

            public function execute(\App\Models\PipelineRun $run, PipelineRunStep $step, ?string $input): PipelineStepResult
            {
                if ($step->position === 1) {
                    $this->firstStepHandled = true;
                    return new PipelineStepResult('first');
                }

                return new PipelineStepResult('second');
            }
        };

        $this->app->instance(PipelineStepExecutor::class, $fakeExecutor);

        $job = new ProcessPipelineJob($run->id);
        $job->handle(app(PipelineRunProcessingService::class));

        Queue::assertPushedOn(ProcessPipelineJob::QUEUE, ProcessPipelineJob::class);
    }

    /**
     * @return array{Pipeline, PipelineVersion}
     */
    private function createPipelineWithSteps(): array
    {
        $pipeline = Pipeline::query()->create();
        $version = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Processing pipeline',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);
        $pipeline->update(['current_version_id' => $version->id]);

        $this->createStep($pipeline, $version, 1, 'transcribe');
        $this->createStep($pipeline, $version, 2, 'text');

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
