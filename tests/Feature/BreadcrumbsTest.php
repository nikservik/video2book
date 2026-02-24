<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Models\Project;
use App\Models\ProjectTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_projects_index_renders_mobile_breadcrumb_wrapping_classes(): void
    {
        $response = $this->get(route('projects.index'));

        $response
            ->assertStatus(200)
            ->assertSee('flex flex-wrap items-center gap-x-3 gap-y-2 pl-7', false)
            ->assertSee('class="-ml-7 shrink-0 md:ml-0"', false)
            ->assertSee('flex min-w-0 max-w-full flex-nowrap items-center gap-3 text-sm"', false)
            ->assertSee('max-w-full font-medium', false);
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
                'Урок',
                'Пайплайн ран • v1',
            ], false);

        $content = $response->getContent();

        $this->assertDoesNotMatchRegularExpression('/<a[^>]*>\s*Урок\s*<\/a>/', $content);
        $this->assertDoesNotMatchRegularExpression('/<a[^>]*>\s*Пайплайн ран • v1\s*<\/a>/', $content);
    }

    public function test_project_run_page_hides_version_suffix_in_breadcrumbs_for_zero_access_level_user(): void
    {
        [$project, $pipelineRun] = $this->createProjectRun();

        $user = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_USER,
            'access_token' => (string) Str::uuid(),
        ]);

        $response = $this
            ->withCookie((string) config('simple_auth.cookie_name'), (string) $user->access_token)
            ->get(route('projects.runs.show', [
                'project' => $project,
                'pipelineRun' => $pipelineRun,
            ]));

        $response
            ->assertStatus(200)
            ->assertSeeInOrder([
                'aria-label="Breadcrumb"',
                'Проекты',
                'Проект Ран',
                'Урок',
                'Пайплайн ран',
            ], false)
            ->assertDontSee('Пайплайн ран • v1');
    }

    public function test_pipeline_pages_render_breadcrumbs_paths(): void
    {
        $indexResponse = $this->get(route('pipelines.index'));

        $indexResponse
            ->assertStatus(200)
            ->assertSeeInOrder([
                'aria-label="Breadcrumb"',
                'Шаблоны',
            ], false);

        $pipeline = Pipeline::query()->create();
        $pipelineVersion = $pipeline->versions()->create([
            'version' => 3,
            'title' => 'Пайплайн страницы',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);
        $pipeline->update(['current_version_id' => $pipelineVersion->id]);

        $showResponse = $this->get(route('pipelines.show', $pipeline));

        $showResponse
            ->assertStatus(200)
            ->assertSeeInOrder([
                'aria-label="Breadcrumb"',
                'Шаблоны',
                'Пайплайн страницы',
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
