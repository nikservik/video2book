<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_expected_content_and_controls(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertStatus(200)
            ->assertSee('Главная')
            ->assertSee('Последние измененные проекты')
            ->assertSee('Очередь обработки')
            ->assertSee('data-theme-set="light"', false)
            ->assertSee('data-theme-set="dark"', false)
            ->assertSee('data-settings-trigger', false)
            ->assertDontSee('View notifications')
            ->assertDontSee('Open user menu');
    }

    public function test_home_page_shows_only_five_latest_updated_projects(): void
    {
        $createdProjects = [];

        $projects = [
            ['name' => 'Архивный проект', 'updated_at' => Carbon::parse('2026-01-01 10:00:00')],
            ['name' => 'Проект Альфа', 'updated_at' => Carbon::parse('2026-01-05 10:00:00')],
            ['name' => 'Проект Бета', 'updated_at' => Carbon::parse('2026-01-10 10:00:00')],
            ['name' => 'Проект Гамма', 'updated_at' => Carbon::parse('2026-01-15 10:00:00')],
            ['name' => 'Проект Дельта', 'updated_at' => Carbon::parse('2026-01-20 10:00:00')],
            ['name' => 'Проект Эпсилон', 'updated_at' => Carbon::parse('2026-01-25 10:00:00')],
        ];

        foreach ($projects as $projectData) {
            $project = Project::query()->create([
                'name' => $projectData['name'],
                'tags' => null,
            ]);

            $project->timestamps = false;
            $project->forceFill([
                'created_at' => $projectData['updated_at']->copy()->subDay(),
                'updated_at' => $projectData['updated_at'],
            ])->saveQuietly();

            $createdProjects[] = $project;
        }

        $response = $this->get(route('home'));

        $response
            ->assertStatus(200)
            ->assertSeeInOrder([
                'Проект Эпсилон',
                'Проект Дельта',
                'Проект Гамма',
                'Проект Бета',
                'Проект Альфа',
            ])
            ->assertDontSee('Архивный проект');

        foreach (array_slice(array_reverse($createdProjects), 0, 5) as $project) {
            $response->assertSee(route('projects.show', $project), false);
        }
    }

    public function test_home_page_does_not_render_breadcrumbs(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertStatus(200)
            ->assertDontSee('data-breadcrumbs', false)
            ->assertDontSee('aria-label="Breadcrumb"', false);
    }
}
