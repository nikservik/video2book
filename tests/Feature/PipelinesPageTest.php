<?php

namespace Tests\Feature;

use App\Models\Pipeline;
use App\Models\PipelineVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('Пайплайны')
            ->assertSee('Добавить пайплайн')
            ->assertSee(route('pipelines.create'), false)
            ->assertSee('grid grid-cols-1 gap-6 lg:grid-cols-3', false)
            ->assertSee('Пайплайн Альфа')
            ->assertSee('Пайплайн Бета')
            ->assertSee('v3')
            ->assertSee('v8')
            ->assertSee('Описание Альфы')
            ->assertSee('Описание Беты')
            ->assertSee('text-sm text-gray-600 dark:text-gray-300', false);
    }

    public function test_create_pipeline_page_renders_as_separate_component_page(): void
    {
        $response = $this->get(route('pipelines.create'));

        $response
            ->assertStatus(200)
            ->assertSee('Добавить пайплайн')
            ->assertSee('Страница создания пайплайна')
            ->assertSeeInOrder([
                'aria-label="Breadcrumb"',
                'Пайплайны',
                'Добавить пайплайн',
            ], false);
    }
}
