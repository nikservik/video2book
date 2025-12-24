<?php

namespace Tests\Feature;

use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\Step;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_flow_supports_all_actions(): void
    {
        $user = User::factory()->create();

        $createResponse = $this->postJson('/api/pipelines', [
            'title' => 'Default pipeline',
            'description' => 'Initial pipeline for tests',
            'changelog' => 'Initial version',
            'created_by' => $user->id,
            'steps' => ['Transcribe', 'Summaries'],
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.current_version.version', 1)
            ->assertJsonCount(2, 'data.steps');

        $pipelineId = $createResponse->json('data.id');
        $pipeline = Pipeline::with('steps')->findOrFail($pipelineId);

        foreach ($pipeline->steps as $step) {
            $response = $this->postJson("/api/pipelines/{$pipelineId}/steps/{$step->id}/initial-version", [
                'type' => 'text',
                'description' => "{$step->name} description",
                'prompt' => 'Prompt '.$step->name,
                'settings' => ['foo' => 'bar'],
            ]);

            $response->assertCreated()
                ->assertJsonPath('data.type', 'text')
                ->assertJsonPath('data.version', 1);
        }

        $version = PipelineVersion::findOrFail($pipeline->fresh()->current_version_id);
        $this->assertCount(2, $version->versionSteps()->get());

        $addStepResponse = $this->postJson("/api/pipelines/{$pipelineId}/steps", [
            'name' => 'Glossary',
            'type' => 'glossary',
            'description' => 'Glossary description',
            'prompt' => 'Glossary prompt',
            'settings' => ['tone' => 'formal'],
            'changelog_entry' => 'Added glossary step',
            'created_by' => $user->id,
        ]);

        $addStepResponse->assertCreated()
            ->assertJsonPath('data.current_version.version', 2)
            ->assertJsonCount(3, 'data.current_version.steps');

        $pipeline->refresh();
        $this->assertEquals(2, $pipeline->currentVersion->version);
        $this->assertCount(3, $pipeline->currentVersion->versionSteps()->get());

        $stepToUpdate = $pipeline->steps()->first();
        $updateStepResponse = $this->postJson("/api/pipelines/{$pipelineId}/steps/{$stepToUpdate->id}/versions", [
            'type' => 'text',
            'description' => 'Updated description',
            'prompt' => 'Updated prompt',
            'settings' => ['tone' => 'friendly'],
            'changelog_entry' => 'Tweaked '.$stepToUpdate->name,
            'created_by' => $user->id,
        ]);

        $updateStepResponse->assertOk()
            ->assertJsonPath('data.current_version.version', 3);

        $this->assertEquals(
            2,
            $stepToUpdate->versions()->max('version'),
            'Step version should increment after update.'
        );

        $stepToRemove = $pipeline->steps()->where('name', 'Glossary')->firstOrFail();
        $removeResponse = $this->deleteJson("/api/pipelines/{$pipelineId}/steps/{$stepToRemove->id}", [
            'changelog_entry' => 'Removed glossary step',
            'created_by' => $user->id,
        ]);

        $removeResponse->assertOk()
            ->assertJsonPath('data.current_version.version', 4);
        $this->assertCount(2, $pipeline->fresh()->currentVersion->versionSteps);

        $updatePipelineResponse = $this->putJson("/api/pipelines/{$pipelineId}", [
            'title' => 'Video2Book Default',
            'description' => 'Updated description',
            'changelog_entry' => 'Renamed pipeline',
            'created_by' => $user->id,
        ]);

        $updatePipelineResponse->assertOk()
            ->assertJsonPath('data.current_version.version', 5)
            ->assertJsonPath('data.current_version.title', 'Video2Book Default');

        $archiveResponse = $this->postJson("/api/pipelines/{$pipelineId}/archive");
        $archiveResponse->assertOk()
            ->assertJsonPath('data.current_version.status', 'archived');

        $listResponse = $this->getJson('/api/pipelines');
        $listResponse->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_pipeline_listing_endpoints_provide_detailed_information(): void
    {
        $user = User::factory()->create();

        $pipelineId = $this->postJson('/api/pipelines', [
            'title' => 'Pipeline A',
            'description' => 'Base pipeline',
            'changelog' => 'Initial release',
            'created_by' => $user->id,
            'steps' => ['Audio', 'Text'],
        ])->json('data.id');

        $pipeline = Pipeline::with('steps')->findOrFail($pipelineId);
        foreach ($pipeline->steps as $step) {
            $this->postJson("/api/pipelines/{$pipelineId}/steps/{$step->id}/initial-version", [
                'type' => $step->name === 'Audio' ? 'transcribe' : 'text',
                'description' => 'Description '.$step->name,
                'prompt' => 'Prompt '.$step->name,
                'settings' => ['mode' => 'default'],
            ])->assertCreated();
        }

        $listResponse = $this->getJson('/api/pipelines');
        $listResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.current_version.steps.0.step.name', 'Audio');

        $pipelineResponse = $this->getJson("/api/pipelines/{$pipelineId}/versions");
        $pipelineResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.steps.1.step.name', 'Text');

        $currentVersionId = Pipeline::find($pipelineId)->current_version_id;
        $versionStepsResponse = $this->getJson("/api/pipeline-versions/{$currentVersionId}/steps");
        $versionStepsResponse->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.version.type', 'transcribe');

        $step = Step::where('pipeline_id', $pipelineId)->firstOrFail();
        $stepVersionsResponse = $this->getJson("/api/steps/{$step->id}/versions");
        $stepVersionsResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', $step->name === 'Audio' ? 'transcribe' : 'text');
    }
}
