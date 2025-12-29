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
        $pipeline = Pipeline::with('steps.currentVersion')->findOrFail($pipelineId);

        $this->assertEquals('transcribe', $pipeline->steps->first()->currentVersion?->type);
        $this->assertEquals('draft', $pipeline->steps->first()->currentVersion?->status);
        $this->assertEquals(
            $pipeline->steps->first()->id,
            $pipeline->steps->get(1)->currentVersion?->input_step_id,
            'Последующий шаг должен ссылаться на предыдущий как на источник.'
        );

        $version = PipelineVersion::findOrFail($pipeline->current_version_id);
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
            'name' => 'Updated '.$stepToUpdate->id,
            'type' => 'text',
            'description' => 'Updated description',
            'prompt' => 'Updated prompt',
            'settings' => ['tone' => 'friendly'],
            'changelog_entry' => 'Tweaked '.$stepToUpdate->currentVersion->name,
            'created_by' => $user->id,
            'mode' => 'new_version',
        ]);

        $updateStepResponse->assertOk()
            ->assertJsonPath('data.current_version.version', 3);

        $this->assertEquals(
            2,
            $stepToUpdate->versions()->max('version'),
            'Step version should increment after update.'
        );

        $stepToRemove = $pipeline->steps()
            ->whereHas('currentVersion', fn ($query) => $query->where('name', 'Glossary'))
            ->firstOrFail();
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
            'mode' => 'new_version',
        ]);

        $updatePipelineResponse->assertOk()
            ->assertJsonPath('data.current_version.version', 5)
            ->assertJsonPath('data.current_version.title', 'Video2Book Default');

        $currentVersionId = Pipeline::findOrFail($pipelineId)->current_version_id;
        $reorderResponse = $this->postJson("/api/pipelines/{$pipelineId}/steps/reorder", [
            'version_id' => $currentVersionId,
            'from_position' => 2,
            'to_position' => 1,
        ]);

        $reorderResponse->assertOk()
            ->assertJsonPath('data.current_version.version', 6)
            ->assertJsonPath('data.current_version.steps.0.step.name', 'Summaries');

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

        $pipeline = Pipeline::with('steps.currentVersion')->findOrFail($pipelineId);

        $listResponse = $this->getJson('/api/pipelines');
        $listResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.current_version.steps.0.step.name', 'Audio');

        $showResponse = $this->getJson("/api/pipelines/{$pipelineId}");
        $showResponse->assertOk()
            ->assertJsonPath('data.id', $pipelineId)
            ->assertJsonPath('data.current_version.steps.1.step.name', 'Text');

        $pipelineResponse = $this->getJson("/api/pipelines/{$pipelineId}/versions");
        $pipelineResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.steps.1.step.name', 'Text');

        $currentVersionId = Pipeline::find($pipelineId)->current_version_id;
        $versionStepsResponse = $this->getJson("/api/pipeline-versions/{$currentVersionId}/steps");
        $versionStepsResponse->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.version.type', 'transcribe')
            ->assertJsonPath('data.1.version.input_step_id', $pipeline->steps->first()->id);

        $step = Step::with('currentVersion')->where('pipeline_id', $pipelineId)->firstOrFail();
        $stepVersionsResponse = $this->getJson("/api/steps/{$step->id}/versions");
        $stepVersionsResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', $step->currentVersion->name === 'Audio' ? 'transcribe' : 'text')
            ->assertJsonPath('data.0.status', 'draft');
    }

    public function test_pipeline_update_can_edit_current_version_without_creating_new_one(): void
    {
        $user = User::factory()->create();

        $pipelineId = $this->postJson('/api/pipelines', [
            'title' => 'Pipeline B',
            'description' => 'Base pipeline',
            'changelog' => 'Initial release',
            'created_by' => $user->id,
            'steps' => ['Audio', 'Text'],
        ])->json('data.id');

        $response = $this->putJson("/api/pipelines/{$pipelineId}", [
            'title' => 'Pipeline B Updated',
            'description' => 'Adjusted copy',
            'mode' => 'current',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_version.version', 1)
            ->assertJsonPath('data.current_version.title', 'Pipeline B Updated')
            ->assertJsonPath('data.current_version.description', 'Adjusted copy');

        $pipeline = Pipeline::findOrFail($pipelineId);
        $this->assertEquals(1, $pipeline->currentVersion->version);
    }

    public function test_step_update_can_edit_current_version_without_triggering_new_pipeline_version(): void
    {
        $user = User::factory()->create();

        $pipelineId = $this->postJson('/api/pipelines', [
            'title' => 'Pipeline C',
            'description' => 'Base pipeline',
            'changelog' => 'Initial release',
            'created_by' => $user->id,
            'steps' => ['Audio', 'Text'],
        ])->json('data.id');

        $step = Pipeline::with('steps')->findOrFail($pipelineId)->steps->first();

        $response = $this->postJson("/api/pipelines/{$pipelineId}/steps/{$step->id}/versions", [
            'name' => 'Edited Audio',
            'type' => 'transcribe',
            'description' => 'Updated description',
            'prompt' => 'Updated prompt',
            'settings' => [
                'provider' => 'anthropic',
                'model' => 'claude-haiku-4-5',
                'temperature' => 0.3,
            ],
            'mode' => 'current',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.current_version.version', 1)
            ->assertJsonPath('data.current_version.steps.0.version.name', 'Edited Audio');

        $this->assertEquals(
            'Edited Audio',
            Step::findOrFail($step->id)->currentVersion?->name,
            'Название текущей версии шага должно обновиться.'
        );
    }
}
