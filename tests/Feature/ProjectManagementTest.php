<?php

namespace Tests\Feature;

use App\Jobs\DownloadLessonAudioJob;
use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_crud_flow(): void
    {
        $createResponse = $this->postJson('/api/projects', [
            'name' => 'Course A',
            'tags' => 'edu,video',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.name', 'Course A')
            ->assertJsonPath('data.tags', 'edu,video');

        $projectId = $createResponse->json('data.id');

        $updateResponse = $this->putJson("/api/projects/{$projectId}", [
            'name' => 'Course A+',
            'tags' => 'edu',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Course A+')
            ->assertJsonPath('data.tags', 'edu');

        $listResponse = $this->getJson('/api/projects');
        $listResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lessons_count', 0);
    }

    public function test_it_creates_project_with_youtube_lessons(): void
    {
        Queue::fake();

        [, $version] = $this->createPipelineWithSteps();

        $response = $this->postJson('/api/projects/youtube', [
            'name' => 'YT Course',
            'pipeline_version_id' => $version->id,
            'lessons' => [
                ['name' => 'Lesson 1', 'url' => 'https://example.com/1'],
                ['name' => 'Lesson 2', 'url' => 'https://example.com/2'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.project.name', 'YT Course')
            ->assertJsonPath('data.project.lessons_count', 2)
            ->assertJsonCount(2, 'data.lessons');

        Queue::assertPushedOn(DownloadLessonAudioJob::QUEUE, DownloadLessonAudioJob::class);
        Queue::assertPushed(DownloadLessonAudioJob::class, 2);

        $lessons = Lesson::query()->with('pipelineRuns')->get();
        $this->assertCount(2, $lessons);
        foreach ($lessons as $lesson) {
            $this->assertTrue(data_get($lesson->settings, 'downloading'));
            $this->assertEquals(1, $lesson->pipelineRuns()->count());
        }
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

        $step = $pipeline->steps()->create();
        $stepVersion = $step->versions()->create([
            'name' => 'Transcription',
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

        return [$pipeline, $version];
    }
}
