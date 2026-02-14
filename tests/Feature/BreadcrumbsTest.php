<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Models\Project;
use App\Models\ProjectTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreadcrumbsTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_index_renders_breadcrumbs_path(): void
    {
        $response = $this->get(route('projects.index'));

        $response
            ->assertStatus(200)
            ->assertSee('data-breadcrumbs', false)
            ->assertSeeInOrder([
                'aria-label="Breadcrumb"',
                'Проекты',
            ], false);
    }

    public function test_project_show_renders_breadcrumbs_path(): void
    {
        $project = Project::query()->create([
            'name' => 'Проект Каскад',
            'tags' => null,
        ]);

        $response = $this->get(route('projects.show', $project));

        $response
            ->assertStatus(200)
            ->assertSeeInOrder([
                'aria-label="Breadcrumb"',
                'Проекты',
                'Проект Каскад',
            ], false);
    }

    public function test_project_lesson_page_renders_breadcrumbs_path(): void
    {
        $response = $this->get(route('projects.lessons.show', [
            'project' => 'demo-project',
            'lesson' => 'demo-lesson',
        ]));

        $response
            ->assertStatus(200)
            ->assertSeeInOrder([
                'aria-label="Breadcrumb"',
                'Проекты',
                'demo-project',
                'demo-lesson',
            ], false);
    }

    public function test_project_run_page_renders_breadcrumbs_path(): void
    {
        [$project, $pipelineRun] = $this->createProjectRun();

        $response = $this->get(route('projects.runs.show', [
            'project' => $project,
            'pipelineRun' => $pipelineRun,
        ]));

        $response
            ->assertStatus(200)
            ->assertSeeInOrder([
                'aria-label="Breadcrumb"',
                'Проекты',
                'Проект Ран',
                'Прогон #'.$pipelineRun->id,
            ], false);
    }

    public function test_pipeline_pages_render_breadcrumbs_paths(): void
    {
        $indexResponse = $this->get(route('pipelines.index'));

        $indexResponse
            ->assertStatus(200)
            ->assertSeeInOrder([
                'aria-label="Breadcrumb"',
                'Пайплайны',
            ], false);

        $stepResponse = $this->get(route('pipelines.steps.show', [
            'pipeline' => 'demo-pipeline',
            'step' => 'demo-step',
        ]));

        $stepResponse
            ->assertStatus(200)
            ->assertSeeInOrder([
                'aria-label="Breadcrumb"',
                'Пайплайны',
                'demo-pipeline',
                'demo-step',
            ], false);
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
            'name' => 'Проект Ран',
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
            'title' => 'Пайплайн ран',
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
