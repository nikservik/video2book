<?php

namespace Tests\Unit\Project;

use App\Actions\Project\CreateProjectLessonFromAudioAction;
use App\Jobs\NormalizeUploadedLessonAudioJob;
use App\Jobs\ProcessPipelineJob;
use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Project;
use App\Models\ProjectTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreateProjectLessonFromAudioActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_lesson_and_queues_uploaded_audio_normalization(): void
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

        $pipelineVersion = $this->createPipelineVersionWithStep();
        $audioFile = UploadedFile::fake()->create('lesson.wav', 512, 'audio/wav');

        $lesson = app(CreateProjectLessonFromAudioAction::class)->handle(
            project: $project,
            lessonName: 'Урок из аудио',
            audioFile: $audioFile,
            pipelineVersionId: $pipelineVersion->id,
        );

        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'project_id' => $project->id,
            'name' => 'Урок из аудио',
            'source_filename' => null,
        ]);
        $this->assertDatabaseHas('pipeline_runs', [
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'queued',
        ]);

        $this->assertSame('queued', data_get($lesson->settings, 'download_status'));
        $this->assertTrue((bool) data_get($lesson->settings, 'downloading'));

        Queue::assertPushedOn(NormalizeUploadedLessonAudioJob::QUEUE, NormalizeUploadedLessonAudioJob::class, function (NormalizeUploadedLessonAudioJob $job) use ($lesson): bool {
            return $job->lessonId === $lesson->id
                && str_starts_with($job->uploadedAudioPath, 'downloader/'.$lesson->id.'/');
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
