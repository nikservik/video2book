<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
