<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Models\Project;
use App\Models\ProjectTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_menu_item_is_active_on_nested_project_page(): void
    {
        $project = Project::query()->create([
            'name' => 'Проект вложенной страницы',
            'tags' => null,
        ]);

        $response = $this->get(route('projects.show', $project));

        $response->assertStatus(200);

        $this->assertMatchesRegularExpression('/data-menu-item="projects"\s+data-active="true"/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/data-menu-item="home"\s+data-active="true"/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/data-menu-item="pipelines"\s+data-active="true"/', $response->getContent());
    }

    public function test_projects_menu_item_is_active_on_project_run_page(): void
    {
        [$project, $pipelineRun] = $this->createProjectRun();

        $response = $this->get(route('projects.runs.show', [
            'project' => $project,
            'pipelineRun' => $pipelineRun,
        ]));

        $response->assertStatus(200);

        $this->assertMatchesRegularExpression('/data-menu-item="projects"\s+data-active="true"/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/data-menu-item="home"\s+data-active="true"/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/data-menu-item="pipelines"\s+data-active="true"/', $response->getContent());
    }

    public function test_pipelines_menu_item_is_active_on_nested_pipeline_page(): void
    {
        $pipeline = Pipeline::query()->create();
        $version = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Вложенная страница пайплайна',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);
        $pipeline->update(['current_version_id' => $version->id]);

        $response = $this->get(route('pipelines.show', $pipeline));

        $response->assertStatus(200);

        $this->assertMatchesRegularExpression('/data-menu-item="pipelines"\s+data-active="true"/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/data-menu-item="home"\s+data-active="true"/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/data-menu-item="projects"\s+data-active="true"/', $response->getContent());
    }

    public function test_activity_menu_item_is_active_on_activity_page(): void
    {
        $response = $this->get(route('activity.index'));

        $response->assertStatus(200);

        $this->assertMatchesRegularExpression('/data-menu-item="activity"\s+data-active="true"/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/data-menu-item="home"\s+data-active="true"/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/data-menu-item="projects"\s+data-active="true"/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/data-menu-item="pipelines"\s+data-active="true"/', $response->getContent());
    }

    /**
     * @return array{0: Project, 1: PipelineRun}
     */
    private function createProjectRun(): array
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект Меню',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        $pipeline = Pipeline::query()->create();
        $version = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Пайплайн',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);

        $pipelineRun = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $version->id,
            'status' => 'queued',
            'state' => [],
        ]);

        return [$project, $pipelineRun];
    }
}
