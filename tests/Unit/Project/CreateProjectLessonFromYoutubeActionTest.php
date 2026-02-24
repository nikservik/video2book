<?php

namespace Tests\Unit\Project;

use App\Actions\Project\CreateProjectLessonFromYoutubeAction;
use App\Jobs\DownloadLessonAudioJob;
use App\Jobs\ProcessPipelineJob;
use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreateProjectLessonFromYoutubeActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_lesson_saves_youtube_url_and_queues_download(): void
    {
        Queue::fake();

        $project = Project::query()->create([
            'name' => 'Проект',
            'tags' => null,
        ]);

        $pipelineVersion = $this->createPipelineVersionWithStep();
        $youtubeUrl = 'https://www.youtube.com/watch?v=abc123';

        $lesson = app(CreateProjectLessonFromYoutubeAction::class)->handle(
            project: $project,
            lessonName: 'Урок из YouTube',
            youtubeUrl: $youtubeUrl,
            pipelineVersionId: $pipelineVersion->id,
        );

        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'project_id' => $project->id,
            'name' => 'Урок из YouTube',
            'source_filename' => null,
        ]);
        $this->assertDatabaseHas('pipeline_runs', [
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'queued',
        ]);

        $this->assertSame($youtubeUrl, data_get($lesson->settings, 'url'));
        $this->assertSame('queued', data_get($lesson->settings, 'download_status'));
        $this->assertTrue((bool) data_get($lesson->settings, 'downloading'));
        $this->assertSame($youtubeUrl, data_get($lesson->settings, 'download_source'));

        Queue::assertPushedOn(DownloadLessonAudioJob::QUEUE, DownloadLessonAudioJob::class, function (DownloadLessonAudioJob $job) use ($lesson, $youtubeUrl): bool {
            return $job->lessonId === $lesson->id
                && $job->sourceUrl === $youtubeUrl;
        });

        Queue::assertNotPushed(ProcessPipelineJob::class);
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
        $pipeline->update(['current_version_id' => $version->id]);

        $step = $pipeline->steps()->create();
        $stepVersion = $step->versions()->create([
            'name' => 'Транскрибация',
            'type' => 'transcribe',
            'version' => 1,
            'description' => null,
            'prompt' => 'Transcribe audio',
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

        return $version;
    }
}
