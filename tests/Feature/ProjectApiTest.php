<?php

namespace Tests\Feature;

use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_tag_and_project_flow(): void
    {
        Storage::fake('local');

        $version = $this->createPipelineVersion();

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

        $createProjectResponse = $this->postJson('/api/projects', [
            'name' => 'First Project',
            'tag' => 'demo',
            'pipeline_version_id' => $version->id,
            'settings' => ['quality' => 'high'],
        ]);

        $createProjectResponse->assertCreated()
            ->assertJsonPath('data.tag', 'demo');

        $projectId = $createProjectResponse->json('data.id');

        $uploadResponse = $this->post("/api/projects/{$projectId}/audio", [
            'file' => UploadedFile::fake()->create('normalized.mp3', 100, 'audio/mpeg'),
        ]);

        $uploadResponse->assertOk()
            ->assertJsonPath('data.source_filename', 'projects/'.$projectId.'.mp3');

        Storage::disk('local')->assertExists('projects/'.$projectId.'.mp3');

        $updateProjectResponse = $this->putJson("/api/projects/{$projectId}", [
            'name' => 'Renamed Project',
            'tag' => 'demo',
            'pipeline_version_id' => $version->id,
            'settings' => ['quality' => 'medium'],
        ]);

        $updateProjectResponse->assertOk()
            ->assertJsonPath('data.name', 'Renamed Project')
            ->assertJsonPath('data.settings.quality', 'medium');

        $listResponse = $this->getJson('/api/projects?tag=demo&search=renamed');
        $listResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Renamed Project');

        $deleteTagAttempt = $this->delete('/api/project-tags/demo');
        $deleteTagAttempt->assertStatus(422);

        Project::query()->delete();
        $deleteTagResponse = $this->delete('/api/project-tags/demo');
        $deleteTagResponse->assertNoContent();
    }

    private function createPipelineVersion(): PipelineVersion
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

        return $version;
    }
}
