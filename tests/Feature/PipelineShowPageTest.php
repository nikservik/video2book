<?php

namespace Tests\Feature;

use App\Livewire\PipelineShowPage;
use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Step;
use App\Models\StepVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PipelineShowPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_show_page_renders_current_version_with_steps_and_controls(): void
    {
        [$pipeline, $versionOne, $versionTwo] = $this->createPipelineWithTwoVersions();

        $response = $this->get(route('pipelines.show', $pipeline));

        $response
            ->assertStatus(200)
            ->assertSee('Пайплайн Текущий')
            ->assertSee('v2')
            ->assertSee('Транскрибация v2')
            ->assertSee('Сводка v2')
            ->assertSee('Транскрибация v2')
            ->assertSee('gpt-5-mini')
            ->assertSee('Редактировать версию')
            ->assertSee('Архивировать версию')
            ->assertDontSee('Вернуть из архива')
            ->assertDontSee('Редактировать пайплайн')
            ->assertDontSee('Удалить пайплайн')
            ->assertSee('Версия 2')
            ->assertSee('Версия 1')
            ->assertSee('data-pipeline-version="'.$versionTwo->id.'"', false)
            ->assertSee('data-active="true"', false)
            ->assertSee('data-pipeline-version="'.$versionOne->id.'"', false)
            ->assertSee('data-version-status="active"', false)
            ->assertSee('data-current-version-icon', false)
            ->assertSee('data-step-delete', false);
    }

    public function test_pipeline_show_page_can_switch_selected_version(): void
    {
        [$pipeline, $versionOne, $versionTwo] = $this->createPipelineWithTwoVersions();

        Livewire::test(PipelineShowPage::class, ['pipeline' => $pipeline])
            ->assertSet('selectedVersionId', $versionTwo->id)
            ->assertSee('Транскрибация v2')
            ->assertSee('Сводка v2')
            ->call('selectVersion', $versionOne->id)
            ->assertSet('selectedVersionId', $versionOne->id)
            ->assertSee('Транскрибация v1')
            ->assertSee('Сводка v1')
            ->assertDontSee('Сводка v2');
    }

    public function test_pipeline_show_page_can_archive_and_restore_selected_version(): void
    {
        [$pipeline, , $versionTwo] = $this->createPipelineWithTwoVersions();

        Livewire::test(PipelineShowPage::class, ['pipeline' => $pipeline])
            ->assertSet('selectedVersionId', $versionTwo->id)
            ->assertSee('Архивировать версию')
            ->assertDontSee('Вернуть из архива')
            ->call('toggleSelectedVersionArchiveStatus')
            ->assertSee('Вернуть из архива')
            ->assertSee('data-version-status="archived"', false);

        $this->assertDatabaseHas('pipeline_versions', [
            'id' => $versionTwo->id,
            'status' => 'archived',
        ]);

        Livewire::test(PipelineShowPage::class, ['pipeline' => $pipeline->fresh()])->call('toggleSelectedVersionArchiveStatus');

        $this->assertDatabaseHas('pipeline_versions', [
            'id' => $versionTwo->id,
            'status' => 'active',
        ]);
    }

    /**
     * @return array{0: Pipeline, 1: PipelineVersion, 2: PipelineVersion}
     */
    private function createPipelineWithTwoVersions(): array
    {
        $pipeline = Pipeline::query()->create();

        $versionOne = PipelineVersion::query()->create([
            'pipeline_id' => $pipeline->id,
            'version' => 1,
            'title' => 'Пайплайн Базовый',
            'description' => 'Описание версии 1',
            'changelog' => null,
            'created_by' => null,
            'status' => 'active',
        ]);

        $versionTwo = PipelineVersion::query()->create([
            'pipeline_id' => $pipeline->id,
            'version' => 2,
            'title' => 'Пайплайн Текущий',
            'description' => 'Описание версии 2',
            'changelog' => null,
            'created_by' => null,
            'status' => 'active',
        ]);

        $transcribeStep = Step::query()->create([
            'pipeline_id' => $pipeline->id,
            'current_version_id' => null,
        ]);

        $textStep = Step::query()->create([
            'pipeline_id' => $pipeline->id,
            'current_version_id' => null,
        ]);

        $transcribeV1 = StepVersion::query()->create([
            'step_id' => $transcribeStep->id,
            'input_step_id' => null,
            'name' => 'Транскрибация v1',
            'type' => 'transcribe',
            'version' => 1,
            'description' => 'Первичная транскрибация',
            'prompt' => null,
            'settings' => ['model' => 'whisper-1'],
            'status' => 'active',
        ]);

        $textV1 = StepVersion::query()->create([
            'step_id' => $textStep->id,
            'input_step_id' => $transcribeStep->id,
            'name' => 'Сводка v1',
            'type' => 'text',
            'version' => 1,
            'description' => 'Краткая сводка',
            'prompt' => null,
            'settings' => ['model' => 'gpt-4o-mini'],
            'status' => 'active',
        ]);

        $transcribeV2 = StepVersion::query()->create([
            'step_id' => $transcribeStep->id,
            'input_step_id' => null,
            'name' => 'Транскрибация v2',
            'type' => 'transcribe',
            'version' => 2,
            'description' => 'Улучшенная транскрибация',
            'prompt' => null,
            'settings' => ['model' => 'whisper-1'],
            'status' => 'active',
        ]);

        $textV2 = StepVersion::query()->create([
            'step_id' => $textStep->id,
            'input_step_id' => $transcribeStep->id,
            'name' => 'Сводка v2',
            'type' => 'text',
            'version' => 2,
            'description' => 'Улучшенная сводка',
            'prompt' => null,
            'settings' => ['model' => 'gpt-5-mini'],
            'status' => 'active',
        ]);

        $transcribeStep->update(['current_version_id' => $transcribeV2->id]);
        $textStep->update(['current_version_id' => $textV2->id]);

        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $versionOne->id,
            'step_version_id' => $transcribeV1->id,
            'position' => 1,
        ]);
        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $versionOne->id,
            'step_version_id' => $textV1->id,
            'position' => 2,
        ]);

        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $versionTwo->id,
            'step_version_id' => $transcribeV2->id,
            'position' => 1,
        ]);
        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $versionTwo->id,
            'step_version_id' => $textV2->id,
            'position' => 2,
        ]);

        $pipeline->update(['current_version_id' => $versionTwo->id]);

        return [$pipeline, $versionOne, $versionTwo];
    }
}
