<?php

namespace Tests\Feature;

use App\Jobs\DownloadLessonAudioJob;
use App\Jobs\NormalizeUploadedLessonAudioJob;
use App\Jobs\ProcessPipelineJob;
use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Project;
use App\Models\ProjectTag;
use App\Services\Pipeline\PipelineEventBroadcaster;
use App\Services\Pipeline\PipelineRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class LessonDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_dispatches_download_job_for_lesson(): void
    {
        Storage::fake('local');
        Queue::fake();

        [$pipeline, $version] = $this->createPipelineWithSteps();
        $project = Project::query()->create(['name' => 'Course', 'tags' => 'demo']);
        $tag = ProjectTag::query()->create(['slug' => 'default', 'description' => null]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson download',
            'tag' => $tag->slug,
            'settings' => ['quality' => 'high'],
        ]);

        // создаём стартовый прогон, чтобы убедиться что dispatchQueuedRuns сможет его обработать
        app(PipelineRunService::class)->createRun($lesson, $version, dispatchJob: false);

        $response = $this->postJson("/api/lessons/{$lesson->id}/download", [
            'url' => 'https://youtube.com/watch?v=demo',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.settings.downloading', true)
            ->assertJsonPath('data.settings.download_status', 'queued');

        Queue::assertPushedOn(DownloadLessonAudioJob::QUEUE, DownloadLessonAudioJob::class, function (DownloadLessonAudioJob $job) use ($lesson) {
            return $job->lessonId === $lesson->id;
        });
    }

    public function test_download_job_marks_lesson_complete_and_dispatches_pipeline(): void
    {
        Storage::fake('local');
        Queue::fake();

        [$pipeline, $version] = $this->createPipelineWithSteps();
        $project = Project::query()->create([
            'name' => 'Course',
            'tags' => 'demo',
            'referer' => 'https://www.somesite.com/',
        ]);
        $tag = ProjectTag::query()->create(['slug' => 'default', 'description' => null]);
        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson download',
            'tag' => $tag->slug,
            'settings' => ['quality' => 'high'],
        ]);

        $run = app(PipelineRunService::class)->createRun($lesson, $version, dispatchJob: false);

        $service = Mockery::mock(\App\Services\Lesson\LessonDownloadService::class);
        $service->shouldReceive('downloadAndNormalize')
            ->once()
            ->andReturnUsing(function (Lesson $invokedLesson, string $url, callable $callback, ?string $referer) use ($lesson) {
                $this->assertSame($lesson->id, $invokedLesson->id);
                $this->assertSame('https://youtube.com/watch?v=ready', $url);
                $this->assertSame('https://www.somesite.com/', $referer);
                $callback(12.3);
                $callback(100.0);

                return [
                    'path' => 'lessons/'.$invokedLesson->id.'.mp3',
                    'duration_seconds' => 4050,
                ];
            });

        $job = new DownloadLessonAudioJob($lesson->id, 'https://youtube.com/watch?v=ready');

        $job->handle(
            $service,
            app(PipelineRunService::class),
            app(PipelineEventBroadcaster::class)
        );

        $lesson->refresh();
        $this->assertSame('lessons/'.$lesson->id.'.mp3', $lesson->source_filename);
        $this->assertFalse(data_get($lesson->settings, 'downloading'));
        $this->assertSame('completed', data_get($lesson->settings, 'download_status'));
        $this->assertEquals(100, data_get($lesson->settings, 'download_progress'));
        $this->assertSame(4050, data_get($lesson->settings, 'audio_duration_seconds'));

        Queue::assertPushedOn(ProcessPipelineJob::QUEUE, ProcessPipelineJob::class, function (ProcessPipelineJob $queuedJob) use ($run) {
            return $queuedJob->pipelineRunId === $run->id;
        });
    }

    public function test_download_job_marks_failure_on_exception(): void
    {
        Storage::fake('local');
        Queue::fake();

        [$pipeline, $version] = $this->createPipelineWithSteps();
        $project = Project::query()->create(['name' => 'Course', 'tags' => 'demo']);
        $tag = ProjectTag::query()->create(['slug' => 'default', 'description' => null]);
        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson failure',
            'tag' => $tag->slug,
            'settings' => ['quality' => 'high'],
        ]);

        app(PipelineRunService::class)->createRun($lesson, $version, dispatchJob: false);

        $service = Mockery::mock(\App\Services\Lesson\LessonDownloadService::class);
        $service->shouldReceive('downloadAndNormalize')
            ->once()
            ->withArgs(function (Lesson $invokedLesson, string $url, callable $callback, ?string $referer) use ($lesson): bool {
                $this->assertSame($lesson->id, $invokedLesson->id);
                $this->assertSame('https://youtube.com/watch?v=broken', $url);
                $this->assertNull($referer);

                return true;
            })
            ->andThrow(new RuntimeException('network error'));

        $job = new DownloadLessonAudioJob($lesson->id, 'https://youtube.com/watch?v=broken');

        try {
            $job->handle(
                $service,
                app(PipelineRunService::class),
                app(PipelineEventBroadcaster::class)
            );
            $this->fail('Job should throw exception');
        } catch (RuntimeException $exception) {
            $this->assertSame('network error', $exception->getMessage());
        }

        $lesson->refresh();
        $this->assertFalse(data_get($lesson->settings, 'downloading'));
        $this->assertSame('failed', data_get($lesson->settings, 'download_status'));
        $this->assertSame('network error', data_get($lesson->settings, 'download_error'));
    }

    public function test_normalize_uploaded_audio_job_marks_lesson_complete_and_dispatches_pipeline(): void
    {
        Storage::fake('local');
        Queue::fake();

        [$pipeline, $version] = $this->createPipelineWithSteps();
        $project = Project::query()->create([
            'name' => 'Course',
            'tags' => 'demo',
        ]);
        $tag = ProjectTag::query()->create(['slug' => 'default', 'description' => null]);
        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson uploaded audio',
            'tag' => $tag->slug,
            'settings' => [
                'quality' => 'high',
                'downloading' => true,
                'download_status' => 'queued',
                'download_source' => 'uploaded_audio',
            ],
        ]);

        $run = app(PipelineRunService::class)->createRun($lesson, $version, dispatchJob: false);
        Storage::disk('local')->put('downloader/'.$lesson->id.'/abc/uploaded.wav', 'audio');

        $service = Mockery::mock(\App\Services\Lesson\LessonDownloadService::class);
        $service->shouldReceive('normalizeStoredAudio')
            ->once()
            ->andReturnUsing(function (Lesson $invokedLesson, string $sourcePath) use ($lesson): array {
                $this->assertSame($lesson->id, $invokedLesson->id);
                $this->assertSame('downloader/'.$lesson->id.'/abc/uploaded.wav', $sourcePath);

                return [
                    'path' => 'lessons/'.$invokedLesson->id.'.mp3',
                    'duration_seconds' => 1830,
                ];
            });

        $job = new NormalizeUploadedLessonAudioJob($lesson->id, 'downloader/'.$lesson->id.'/abc/uploaded.wav');

        $job->handle(
            $service,
            app(PipelineRunService::class),
            app(PipelineEventBroadcaster::class)
        );

        $lesson->refresh();
        $this->assertSame('lessons/'.$lesson->id.'.mp3', $lesson->source_filename);
        $this->assertFalse(data_get($lesson->settings, 'downloading'));
        $this->assertSame('completed', data_get($lesson->settings, 'download_status'));
        $this->assertEquals(100, data_get($lesson->settings, 'download_progress'));
        $this->assertSame(1830, data_get($lesson->settings, 'audio_duration_seconds'));

        Queue::assertPushedOn(ProcessPipelineJob::QUEUE, ProcessPipelineJob::class, function (ProcessPipelineJob $queuedJob) use ($run) {
            return $queuedJob->pipelineRunId === $run->id;
        });
    }

    public function test_normalize_uploaded_audio_job_marks_failure_on_exception(): void
    {
        Storage::fake('local');
        Queue::fake();

        [$pipeline, $version] = $this->createPipelineWithSteps();
        $project = Project::query()->create(['name' => 'Course', 'tags' => 'demo']);
        $tag = ProjectTag::query()->create(['slug' => 'default', 'description' => null]);
        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson uploaded failure',
            'tag' => $tag->slug,
            'settings' => [
                'quality' => 'high',
                'downloading' => true,
                'download_status' => 'queued',
                'download_source' => 'uploaded_audio',
            ],
        ]);

        app(PipelineRunService::class)->createRun($lesson, $version, dispatchJob: false);
        Storage::disk('local')->put('downloader/'.$lesson->id.'/abc/uploaded.wav', 'audio');

        $service = Mockery::mock(\App\Services\Lesson\LessonDownloadService::class);
        $service->shouldReceive('normalizeStoredAudio')
            ->once()
            ->withArgs(function (Lesson $invokedLesson, string $sourcePath) use ($lesson): bool {
                $this->assertSame($lesson->id, $invokedLesson->id);
                $this->assertSame('downloader/'.$lesson->id.'/abc/uploaded.wav', $sourcePath);

                return true;
            })
            ->andThrow(new RuntimeException('normalize error'));

        $job = new NormalizeUploadedLessonAudioJob($lesson->id, 'downloader/'.$lesson->id.'/abc/uploaded.wav');

        try {
            $job->handle(
                $service,
                app(PipelineRunService::class),
                app(PipelineEventBroadcaster::class)
            );
            $this->fail('Job should throw exception');
        } catch (RuntimeException $exception) {
            $this->assertSame('normalize error', $exception->getMessage());
        }

        $lesson->refresh();
        $this->assertFalse(data_get($lesson->settings, 'downloading'));
        $this->assertSame('failed', data_get($lesson->settings, 'download_status'));
        $this->assertSame('normalize error', data_get($lesson->settings, 'download_error'));
    }

    /**
     * @return array{0: Pipeline, 1: PipelineVersion}
     */
    private function createPipelineWithSteps(): array
    {
        $pipeline = Pipeline::query()->create();
        $version = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Test pipeline',
            'description' => 'Test description',
            'changelog' => 'Init',
            'created_by' => null,
            'status' => 'active',
        ]);
        $pipeline->update(['current_version_id' => $version->id]);

        $step = $pipeline->steps()->create();
        $stepVersion = $step->versions()->create([
            'name' => 'Transcription',
            'type' => 'transcribe',
            'version' => 1,
            'description' => 'Transcribe audio',
            'prompt' => 'Transcribe the supplied audio',
            'settings' => [
                'provider' => 'openai',
                'model' => 'whisper-1',
                'temperature' => 0,
            ],
            'status' => 'active',
        ]);
        $step->update(['current_version_id' => $stepVersion->id]);

        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $version->id,
            'step_version_id' => $stepVersion->id,
            'position' => 1,
        ]);

        return [$pipeline, $version];
    }
}
