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
            ->assertSee('Сделать текущей версией')
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
            ->assertSee('data-set-current-version-button', false)
            ->assertSee('data-disabled="true"', false)
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

    public function test_pipeline_show_page_step_edit_modal_can_be_opened_and_closed(): void
    {
        [$pipeline] = $this->createPipelineWithTwoVersions();

        $stepVersion = StepVersion::query()
            ->where('name', 'Сводка v2')
            ->firstOrFail();

        Livewire::test(PipelineShowPage::class, ['pipeline' => $pipeline])
            ->assertSet('showStepEditModal', false)
            ->call('openStepEditModal', $stepVersion->id)
            ->assertSet('showStepEditModal', true)
            ->assertSet('editingStepVersionId', $stepVersion->id)
            ->assertSee('data-step-edit-modal', false)
            ->assertSee('Сохранить')
            ->assertSee('новая версия')
            ->call('closeStepEditModal')
            ->assertSet('showStepEditModal', false)
            ->assertDontSee('data-step-edit-modal', false);
    }

    public function test_pipeline_show_page_version_edit_modal_can_be_opened_and_closed(): void
    {
        [$pipeline, , $versionTwo] = $this->createPipelineWithTwoVersions();

        Livewire::test(PipelineShowPage::class, ['pipeline' => $pipeline])
            ->assertSet('showEditVersionModal', false)
            ->call('openEditVersionModal')
            ->assertSet('showEditVersionModal', true)
            ->assertSet('editableVersionTitle', $versionTwo->title)
            ->assertSet('editableVersionDescription', $versionTwo->description)
            ->assertSee('data-edit-version-modal', false)
            ->call('closeEditVersionModal')
            ->assertSet('showEditVersionModal', false)
            ->assertDontSee('data-edit-version-modal', false);
    }

    public function test_pipeline_show_page_can_save_selected_pipeline_version_title_and_description(): void
    {
        [$pipeline, , $versionTwo] = $this->createPipelineWithTwoVersions();

        Livewire::test(PipelineShowPage::class, ['pipeline' => $pipeline])
            ->call('openEditVersionModal')
            ->set('editableVersionTitle', 'Пайплайн Текущий Обновленный')
            ->set('editableVersionDescription', 'Обновленное описание версии')
            ->call('saveVersion')
            ->assertSet('showEditVersionModal', false)
            ->assertSee('Пайплайн Текущий Обновленный');

        $this->assertDatabaseHas('pipeline_versions', [
            'id' => $versionTwo->id,
            'title' => 'Пайплайн Текущий Обновленный',
            'description' => 'Обновленное описание версии',
        ]);
    }

    public function test_pipeline_show_page_can_save_step_changes_in_current_step_version(): void
    {
        [$pipeline, , $versionTwo] = $this->createPipelineWithTwoVersions();

        $textStepVersion = StepVersion::query()
            ->where('name', 'Сводка v2')
            ->where('version', 2)
            ->firstOrFail();

        $component = Livewire::test(PipelineShowPage::class, ['pipeline' => $pipeline])
            ->call('openStepEditModal', $textStepVersion->id)
            ->set('editStepName', 'Сводка v2 обновленная')
            ->set('editStepDescription', 'Обновленное описание')
            ->set('editStepModel', 'claude-sonnet-4-5')
            ->set('editStepTemperature', 0.6)
            ->set('editStepPrompt', 'Новый промт')
            ->call('saveStep')
            ->assertSet('showStepEditModal', false)
            ->assertSet('selectedVersionId', $versionTwo->id);

        $updatedStepVersion = StepVersion::query()->findOrFail($textStepVersion->id);

        $this->assertSame('Сводка v2 обновленная', $updatedStepVersion->name);
        $this->assertSame('Обновленное описание', $updatedStepVersion->description);
        $this->assertSame('Новый промт', $updatedStepVersion->prompt);
        $this->assertSame('claude-sonnet-4-5', data_get($updatedStepVersion->settings, 'model'));
        $this->assertSame('anthropic', data_get($updatedStepVersion->settings, 'provider'));
        $this->assertSame(0.6, (float) data_get($updatedStepVersion->settings, 'temperature'));

        $this->assertDatabaseCount('pipeline_versions', 2);
        $this->assertDatabaseHas('pipelines', [
            'id' => $pipeline->id,
            'current_version_id' => $versionTwo->id,
        ]);

        $component->assertSet('editingStepVersionId', null);
    }

    public function test_pipeline_show_page_can_save_step_as_new_version_and_new_pipeline_version_for_current_selection(): void
    {
        [$pipeline, , $versionTwo] = $this->createPipelineWithTwoVersions();

        $transcribeV2 = StepVersion::query()
            ->where('name', 'Транскрибация v2')
            ->where('version', 2)
            ->firstOrFail();

        $textV2 = StepVersion::query()
            ->where('name', 'Сводка v2')
            ->where('version', 2)
            ->firstOrFail();

        Livewire::test(PipelineShowPage::class, ['pipeline' => $pipeline])
            ->call('openStepEditModal', $textV2->id)
            ->set('editStepName', 'Сводка v3')
            ->set('editStepDescription', 'Описание v3')
            ->set('editStepPrompt', 'Промт v3')
            ->set('editStepModel', 'gpt-5.1')
            ->set('editStepChangelogEntry', 'Создана новая версия шага')
            ->call('saveStepAsNewVersion')
            ->assertSet('showStepEditModal', false);

        $newPipelineVersion = PipelineVersion::query()
            ->where('pipeline_id', $pipeline->id)
            ->where('version', 3)
            ->firstOrFail();

        $this->assertSame('- Изменения в шаге «Сводка v3»: Создана новая версия шага', $newPipelineVersion->changelog);

        $pipeline->refresh();
        $this->assertSame($newPipelineVersion->id, $pipeline->current_version_id);

        $newStepVersion = StepVersion::query()
            ->where('step_id', $textV2->step_id)
            ->where('version', 3)
            ->firstOrFail();

        $this->assertSame('Сводка v3', $newStepVersion->name);
        $this->assertSame('Описание v3', $newStepVersion->description);
        $this->assertSame('Промт v3', $newStepVersion->prompt);
        $this->assertSame('gpt-5.1', data_get($newStepVersion->settings, 'model'));
        $this->assertSame('openai', data_get($newStepVersion->settings, 'provider'));
        $this->assertSame(1.0, (float) data_get($newStepVersion->settings, 'temperature'));

        $this->assertDatabaseHas('pipeline_version_steps', [
            'pipeline_version_id' => $newPipelineVersion->id,
            'position' => 1,
            'step_version_id' => $transcribeV2->id,
        ]);
        $this->assertDatabaseHas('pipeline_version_steps', [
            'pipeline_version_id' => $newPipelineVersion->id,
            'position' => 2,
            'step_version_id' => $newStepVersion->id,
        ]);

        $this->assertDatabaseCount('pipeline_versions', 3);
        $this->assertDatabaseHas('pipelines', [
            'id' => $pipeline->id,
            'current_version_id' => $newPipelineVersion->id,
        ]);
        $this->assertDatabaseMissing('pipelines', [
            'id' => $pipeline->id,
            'current_version_id' => $versionTwo->id,
        ]);
    }

    public function test_pipeline_show_page_keeps_current_pipeline_version_when_new_version_saved_from_non_current_selection(): void
    {
        [$pipeline, $versionOne, $versionTwo] = $this->createPipelineWithTwoVersions();

        $textV1 = StepVersion::query()
            ->where('name', 'Сводка v1')
            ->where('version', 1)
            ->firstOrFail();

        $component = Livewire::test(PipelineShowPage::class, ['pipeline' => $pipeline])
            ->call('selectVersion', $versionOne->id)
            ->call('openStepEditModal', $textV1->id)
            ->set('editStepName', 'Сводка из v1 в новую версию')
            ->set('editStepChangelogEntry', 'Новая версия из не-текущей')
            ->call('saveStepAsNewVersion')
            ->assertSet('showStepEditModal', false);

        $newPipelineVersion = PipelineVersion::query()
            ->where('pipeline_id', $pipeline->id)
            ->where('version', 3)
            ->firstOrFail();

        $pipeline->refresh();
        $this->assertSame($versionTwo->id, $pipeline->current_version_id);
        $this->assertNotSame($newPipelineVersion->id, $pipeline->current_version_id);

        $component->assertSet('selectedVersionId', $newPipelineVersion->id);
    }

    public function test_pipeline_show_page_can_set_selected_version_as_current(): void
    {
        [$pipeline, $versionOne, $versionTwo] = $this->createPipelineWithTwoVersions();

        $component = Livewire::test(PipelineShowPage::class, ['pipeline' => $pipeline])
            ->assertSet('selectedVersionId', $versionTwo->id)
            ->assertSee('data-disabled="true"', escape: false)
            ->call('selectVersion', $versionOne->id)
            ->assertSet('selectedVersionId', $versionOne->id)
            ->assertSee('data-disabled="false"', escape: false);

        $component->call('makeSelectedVersionCurrent');

        $this->assertDatabaseHas('pipelines', [
            'id' => $pipeline->id,
            'current_version_id' => $versionOne->id,
        ]);

        Livewire::test(PipelineShowPage::class, ['pipeline' => $pipeline->fresh()])
            ->assertSet('selectedVersionId', $versionOne->id)
            ->assertSee('data-disabled="true"', escape: false);
    }

    public function test_pipeline_show_page_cannot_set_archived_version_as_current(): void
    {
        [$pipeline, $versionOne, $versionTwo] = $this->createPipelineWithTwoVersions();

        $versionOne->update(['status' => 'archived']);

        Livewire::test(PipelineShowPage::class, ['pipeline' => $pipeline->fresh()])
            ->call('selectVersion', $versionOne->id)
            ->assertSet('selectedVersionId', $versionOne->id)
            ->assertSee('data-disabled="true"', escape: false)
            ->call('makeSelectedVersionCurrent');

        $this->assertDatabaseHas('pipelines', [
            'id' => $pipeline->id,
            'current_version_id' => $versionTwo->id,
        ]);
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
            ->assertSee('data-version-status="archived"', false)
            ->assertSee('data-archived-version-icon', false);

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

    public function test_pipeline_show_page_hides_delete_icon_for_first_step(): void
    {
        [$pipeline] = $this->createPipelineWithTwoVersions();

        $firstStepVersion = StepVersion::query()
            ->where('name', 'Транскрибация v2')
            ->firstOrFail();

        $secondStepVersion = StepVersion::query()
            ->where('name', 'Сводка v2')
            ->firstOrFail();

        Livewire::test(PipelineShowPage::class, ['pipeline' => $pipeline])
            ->assertDontSee('data-step-delete="'.$firstStepVersion->id.'"', false)
            ->assertSee('data-step-delete="'.$secondStepVersion->id.'"', false);
    }

    public function test_pipeline_show_page_does_not_open_delete_alert_for_first_step(): void
    {
        [$pipeline] = $this->createPipelineWithTwoVersions();

        $firstStepVersion = StepVersion::query()
            ->where('name', 'Транскрибация v2')
            ->firstOrFail();

        Livewire::test(PipelineShowPage::class, ['pipeline' => $pipeline])
            ->call('openDeleteStepAlert', $firstStepVersion->id)
            ->assertSet('showDeleteStepAlert', false)
            ->assertSet('deletingStepVersionId', null)
            ->assertDontSee('data-delete-step-alert', false);
    }

    public function test_pipeline_show_page_remove_step_creates_new_pipeline_version_with_max_increment_for_selected_version(): void
    {
        [$pipeline, $versionOne, $versionTwo] = $this->createPipelineWithTwoVersions();

        $stepToRemove = StepVersion::query()
            ->where('name', 'Сводка v1')
            ->firstOrFail();

        $component = Livewire::test(PipelineShowPage::class, ['pipeline' => $pipeline])
            ->call('selectVersion', $versionOne->id)
            ->call('openDeleteStepAlert', $stepToRemove->id)
            ->assertSet('showDeleteStepAlert', true)
            ->assertSet('deletingStepVersionId', $stepToRemove->id)
            ->assertSee('data-delete-step-alert', false);

        $this->assertDatabaseCount('pipeline_versions', 2);

        $component->call('confirmDeleteStep')
            ->assertSet('showDeleteStepAlert', false)
            ->assertSee('Транскрибация v1')
            ->assertDontSee('Сводка v1');

        $newVersion = PipelineVersion::query()
            ->where('pipeline_id', $pipeline->id)
            ->where('version', 3)
            ->firstOrFail();

        $component->assertSet('selectedVersionId', $newVersion->id);

        $this->assertDatabaseHas('pipelines', [
            'id' => $pipeline->id,
            'current_version_id' => $versionTwo->id,
        ]);

        $this->assertDatabaseMissing('pipeline_version_steps', [
            'pipeline_version_id' => $newVersion->id,
            'step_version_id' => $stepToRemove->id,
        ]);

        $this->assertStringContainsString(
            '- Удален шаг «Сводка v1»',
            (string) $newVersion->changelog
        );
    }

    public function test_pipeline_show_page_remove_step_relinks_dependent_source_to_removed_step_source_and_writes_changelog(): void
    {
        [$pipeline, $version, $firstStepVersion, $removedStepVersion, $dependentStepVersion] = $this->createPipelineForStepRemovalWithDependentSource();

        Livewire::test(PipelineShowPage::class, ['pipeline' => $pipeline])
            ->assertSet('selectedVersionId', $version->id)
            ->call('openDeleteStepAlert', $removedStepVersion->id)
            ->assertSet('showDeleteStepAlert', true)
            ->call('confirmDeleteStep')
            ->assertSet('showDeleteStepAlert', false);

        $newVersion = PipelineVersion::query()
            ->where('pipeline_id', $pipeline->id)
            ->where('version', 2)
            ->firstOrFail();

        $newVersion->load('versionSteps.stepVersion');

        $dependentNewVersion = $newVersion->versionSteps
            ->map(fn (PipelineVersionStep $item): ?StepVersion => $item->stepVersion)
            ->filter()
            ->first(fn (StepVersion $stepVersion): bool => (int) $stepVersion->step_id === (int) $dependentStepVersion->step_id);

        $this->assertNotNull($dependentNewVersion);
        $this->assertSame($firstStepVersion->step_id, $dependentNewVersion->input_step_id);
        $this->assertGreaterThan($dependentStepVersion->version, $dependentNewVersion->version);

        $this->assertDatabaseHas('steps', [
            'id' => $dependentStepVersion->step_id,
            'current_version_id' => $dependentNewVersion->id,
        ]);

        $this->assertStringContainsString(
            '- Удален шаг «'.$removedStepVersion->name.'»',
            (string) $newVersion->changelog
        );
        $this->assertStringContainsString(
            '- Обновлен источник для шага «'.$dependentStepVersion->name.'» из-за удаления шага «'.$removedStepVersion->name.'»',
            (string) $newVersion->changelog
        );
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

    /**
     * @return array{0: Pipeline, 1: PipelineVersion, 2: StepVersion, 3: StepVersion, 4: StepVersion}
     */
    private function createPipelineForStepRemovalWithDependentSource(): array
    {
        $pipeline = Pipeline::query()->create();

        $version = PipelineVersion::query()->create([
            'pipeline_id' => $pipeline->id,
            'version' => 1,
            'title' => 'Удаление шага',
            'description' => 'Проверка удаления шага с перенастройкой источников',
            'changelog' => null,
            'created_by' => null,
            'status' => 'active',
        ]);

        $firstStep = Step::query()->create([
            'pipeline_id' => $pipeline->id,
            'current_version_id' => null,
        ]);
        $removedStep = Step::query()->create([
            'pipeline_id' => $pipeline->id,
            'current_version_id' => null,
        ]);
        $middleStep = Step::query()->create([
            'pipeline_id' => $pipeline->id,
            'current_version_id' => null,
        ]);
        $dependentStep = Step::query()->create([
            'pipeline_id' => $pipeline->id,
            'current_version_id' => null,
        ]);

        $firstStepVersion = StepVersion::query()->create([
            'step_id' => $firstStep->id,
            'input_step_id' => null,
            'name' => 'Первый шаг',
            'type' => 'transcribe',
            'version' => 1,
            'description' => 'Шаг транскрибации',
            'prompt' => null,
            'settings' => ['model' => 'whisper-1'],
            'status' => 'active',
        ]);

        $removedStepVersion = StepVersion::query()->create([
            'step_id' => $removedStep->id,
            'input_step_id' => $firstStep->id,
            'name' => 'Удаляемый шаг',
            'type' => 'text',
            'version' => 1,
            'description' => 'Шаг, который удаляем',
            'prompt' => null,
            'settings' => ['model' => 'gpt-5-mini'],
            'status' => 'active',
        ]);

        $middleStepVersion = StepVersion::query()->create([
            'step_id' => $middleStep->id,
            'input_step_id' => $firstStep->id,
            'name' => 'Промежуточный шаг',
            'type' => 'glossary',
            'version' => 1,
            'description' => 'Шаг между удаляемым и зависимым',
            'prompt' => null,
            'settings' => ['model' => 'gpt-5-mini'],
            'status' => 'active',
        ]);

        $dependentStepVersion = StepVersion::query()->create([
            'step_id' => $dependentStep->id,
            'input_step_id' => $removedStep->id,
            'name' => 'Зависимый шаг',
            'type' => 'text',
            'version' => 1,
            'description' => 'Шаг, зависящий от удаляемого',
            'prompt' => null,
            'settings' => ['model' => 'gpt-5-mini'],
            'status' => 'active',
        ]);

        $firstStep->update(['current_version_id' => $firstStepVersion->id]);
        $removedStep->update(['current_version_id' => $removedStepVersion->id]);
        $middleStep->update(['current_version_id' => $middleStepVersion->id]);
        $dependentStep->update(['current_version_id' => $dependentStepVersion->id]);

        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $version->id,
            'step_version_id' => $firstStepVersion->id,
            'position' => 1,
        ]);
        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $version->id,
            'step_version_id' => $removedStepVersion->id,
            'position' => 2,
        ]);
        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $version->id,
            'step_version_id' => $middleStepVersion->id,
            'position' => 3,
        ]);
        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $version->id,
            'step_version_id' => $dependentStepVersion->id,
            'position' => 4,
        ]);

        $pipeline->update(['current_version_id' => $version->id]);

        return [$pipeline, $version, $firstStepVersion, $removedStepVersion, $dependentStepVersion];
    }
}
