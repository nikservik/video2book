<?php

namespace Tests\Feature;

use App\Livewire\PipelinesPage;
use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Step;
use App\Models\StepVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PipelinesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipelines_page_shows_list_with_title_version_description_and_add_button(): void
    {
        $firstPipeline = Pipeline::query()->create();
        $firstVersion = PipelineVersion::query()->create([
            'pipeline_id' => $firstPipeline->id,
            'version' => 3,
            'title' => 'Пайплайн Альфа',
            'description' => 'Описание Альфы',
            'changelog' => null,
            'created_by' => null,
            'status' => 'active',
        ]);
        $firstPipeline->update(['current_version_id' => $firstVersion->id]);

        $secondPipeline = Pipeline::query()->create();
        $secondVersion = PipelineVersion::query()->create([
            'pipeline_id' => $secondPipeline->id,
            'version' => 8,
            'title' => 'Пайплайн Бета',
            'description' => 'Описание Беты',
            'changelog' => null,
            'created_by' => null,
            'status' => 'active',
        ]);
        $secondPipeline->update(['current_version_id' => $secondVersion->id]);

        $response = $this->get(route('pipelines.index'));

        $response
            ->assertStatus(200)
            ->assertSee('Шаблоны')
            ->assertSee('Добавить шаблон')
            ->assertSee('data-open-create-pipeline-modal', false)
            ->assertSee('grid grid-cols-1 gap-4 md:gap-6 lg:grid-cols-3', false)
            ->assertSee('Пайплайн Альфа')
            ->assertSee('Пайплайн Бета')
            ->assertSee(route('pipelines.show', $firstPipeline), false)
            ->assertSee(route('pipelines.show', $secondPipeline), false)
            ->assertSee('v3')
            ->assertSee('v8')
            ->assertSee('Описание Альфы')
            ->assertSee('Описание Беты')
            ->assertSee('text-sm text-gray-600 dark:text-gray-300', false);
    }

    public function test_pipelines_page_marks_pipeline_title_as_archived_when_current_version_is_archived(): void
    {
        $pipeline = Pipeline::query()->create();
        $archivedVersion = PipelineVersion::query()->create([
            'pipeline_id' => $pipeline->id,
            'version' => 12,
            'title' => 'Пайплайн Архив',
            'description' => 'Описание архива',
            'changelog' => null,
            'created_by' => null,
            'status' => 'archived',
        ]);
        $pipeline->update(['current_version_id' => $archivedVersion->id]);

        $response = $this->get(route('pipelines.index'));

        $response
            ->assertStatus(200)
            ->assertSee('Пайплайн Архив')
            ->assertSee('text-gray-500 dark:text-gray-400', false)
            ->assertSee('data-archived-current-version-icon', false)
            ->assertDontSee('v12');
    }

    public function test_pipelines_page_create_pipeline_modal_opens_with_default_steps(): void
    {
        Livewire::test(PipelinesPage::class)
            ->assertSet('showCreatePipelineModal', false)
            ->call('openCreatePipelineModal')
            ->assertSet('showCreatePipelineModal', true)
            ->assertSet('createPipelineStepNames.0', 'Транскрибация')
            ->assertSet('createPipelineStepNames.1', 'Структура')
            ->assertSee('data-create-pipeline-modal', false)
            ->call('closeCreatePipelineModal')
            ->assertSet('showCreatePipelineModal', false)
            ->assertDontSee('data-create-pipeline-modal', false);
    }

    public function test_pipelines_page_can_save_pipeline_with_custom_frontend_steps_order(): void
    {
        Livewire::test(PipelinesPage::class)
            ->call('openCreatePipelineModal')
            ->call('savePipeline', 'Пайплайн из фронта', 'Описание', ['Структура', 'Итоги', 'Финал']);

        $pipeline = Pipeline::query()->firstOrFail();
        $version = PipelineVersion::query()->where('pipeline_id', $pipeline->id)->firstOrFail();

        $orderedStepVersions = PipelineVersionStep::query()
            ->where('pipeline_version_id', $version->id)
            ->orderBy('position')
            ->get()
            ->map(fn (PipelineVersionStep $pipelineVersionStep): StepVersion => StepVersion::query()->findOrFail($pipelineVersionStep->step_version_id))
            ->values();

        $this->assertSame('Структура', $orderedStepVersions[0]->name);
        $this->assertSame('Итоги', $orderedStepVersions[1]->name);
        $this->assertSame('Финал', $orderedStepVersions[2]->name);
        $this->assertSame('transcribe', $orderedStepVersions[0]->type);
        $this->assertSame('text', $orderedStepVersions[1]->type);
        $this->assertSame('text', $orderedStepVersions[2]->type);
    }

    public function test_pipelines_page_can_create_pipeline_and_redirect_to_show_page(): void
    {
        $component = Livewire::test(PipelinesPage::class)
            ->call('openCreatePipelineModal')
            ->call('savePipeline', 'Новый пайплайн', 'Описание нового пайплайна', ['Транскрибация', 'Структура']);

        $pipeline = Pipeline::query()->firstOrFail();
        $version = PipelineVersion::query()->where('pipeline_id', $pipeline->id)->firstOrFail();

        $component->assertRedirect(route('pipelines.show', $pipeline));

        $this->assertDatabaseHas('pipelines', [
            'id' => $pipeline->id,
            'current_version_id' => $version->id,
        ]);

        $this->assertDatabaseHas('pipeline_versions', [
            'id' => $version->id,
            'version' => 1,
            'title' => 'Новый пайплайн',
            'description' => 'Описание нового пайплайна',
            'status' => 'archived',
        ]);

        $this->assertDatabaseCount('steps', 2);
        $this->assertDatabaseCount('step_versions', 2);
        $this->assertDatabaseCount('pipeline_version_steps', 2);

        $firstVersionStep = PipelineVersionStep::query()
            ->where('pipeline_version_id', $version->id)
            ->where('position', 1)
            ->firstOrFail();

        $secondVersionStep = PipelineVersionStep::query()
            ->where('pipeline_version_id', $version->id)
            ->where('position', 2)
            ->firstOrFail();

        $firstStepVersion = StepVersion::query()->findOrFail($firstVersionStep->step_version_id);
        $secondStepVersion = StepVersion::query()->findOrFail($secondVersionStep->step_version_id);

        $this->assertSame('transcribe', $firstStepVersion->type);
        $this->assertSame('text', $secondStepVersion->type);
        $this->assertSame('draft', $firstStepVersion->status);
        $this->assertSame('draft', $secondStepVersion->status);
        $this->assertNull($firstStepVersion->input_step_id);
        $this->assertSame($firstStepVersion->step_id, $secondStepVersion->input_step_id);
        $this->assertFalse((bool) data_get($firstStepVersion->settings, 'is_default', false));
        $this->assertTrue((bool) data_get($secondStepVersion->settings, 'is_default', false));

        $firstStep = Step::query()->findOrFail($firstStepVersion->step_id);
        $secondStep = Step::query()->findOrFail($secondStepVersion->step_id);

        $this->assertSame($firstStepVersion->id, $firstStep->current_version_id);
        $this->assertSame($secondStepVersion->id, $secondStep->current_version_id);
    }
}
