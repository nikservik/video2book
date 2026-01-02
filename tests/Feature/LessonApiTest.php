<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Project;
use App\Models\ProjectTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LessonApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lesson_tag_and_pipeline_flow(): void
    {
        Storage::fake('local');
        Queue::fake();

        [$pipeline, $version] = $this->createPipelineWithSteps();
        $project = Project::query()->create(['name' => 'Course', 'tags' => 'demo']);

        $createTagResponse = $this->postJson('/api/project-tags', [
            'slug' => 'demo',
            'description' => 'Demo tag',
        ]);

        $createTagResponse->assertCreated()
            ->assertJsonPath('data.slug', 'demo');

        $updateTagResponse = $this->putJson('/api/project-tags/demo', [
            'description' => 'Updated description',
        ]);

        $updateTagResponse->assertOk()
            ->assertJsonPath('data.description', 'Updated description');

        $createLessonResponse = $this->postJson('/api/lessons', [
            'project_id' => $project->id,
            'name' => 'First Lesson',
            'pipeline_version_id' => $version->id,
            'settings' => ['quality' => 'high'],
        ]);

        $createLessonResponse->assertCreated()
            ->assertJsonPath('data.tag', 'default')
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonPath('data.pipeline_runs.0.pipeline_version.id', $version->id)
            ->assertJsonPath('data.pipeline_runs.0.steps_total', 1)
            ->assertJsonPath('data.pipeline_runs.0.steps_completed', 0)
            ->assertJsonCount(1, 'data.pipeline_runs');

        $this->postJson('/api/lessons', [
            'project_id' => $project->id,
            'name' => 'Tagged Lesson',
            'tag' => 'demo',
            'pipeline_version_id' => $version->id,
            'settings' => ['quality' => 'high'],
        ])->assertCreated()
            ->assertJsonPath('data.tag', 'demo');

        $lessonId = $createLessonResponse->json('data.id');

        $uploadResponse = $this->post("/api/lessons/{$lessonId}/audio", [
            'file' => UploadedFile::fake()->create('normalized.mp3', 100, 'audio/mpeg'),
        ]);

        $uploadResponse->assertOk()
            ->assertJsonPath('data.source_filename', 'lessons/'.$lessonId.'.mp3');

        Storage::disk('local')->assertExists('lessons/'.$lessonId.'.mp3');

        $nextVersion = $this->createPipelineWithSteps()[1];

        $updateLessonResponse = $this->putJson("/api/lessons/{$lessonId}", [
            'project_id' => $project->id,
            'name' => 'Renamed Lesson',
            'pipeline_version_id' => $nextVersion->id,
            'settings' => ['quality' => 'medium'],
        ]);

        $updateLessonResponse->assertOk()
            ->assertJsonPath('data.name', 'Renamed Lesson')
            ->assertJsonPath('data.settings.quality', 'medium')
            ->assertJsonPath('data.pipeline_runs.0.pipeline_version.id', $nextVersion->id)
            ->assertJsonCount(2, 'data.pipeline_runs');

        $listResponse = $this->getJson('/api/lessons?search=renamed');
        $listResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Renamed Lesson');

        $deleteTagAttempt = $this->delete('/api/project-tags/demo');
        $deleteTagAttempt->assertStatus(422);

        Lesson::query()->delete();
        $deleteTagResponse = $this->delete('/api/project-tags/demo');
        $deleteTagResponse->assertNoContent();
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
