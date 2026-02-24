<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
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
            ->assertSee('Свежие проекты')
            ->assertSee('mx-2 md:mx-4 text-lg font-semibold text-gray-900 dark:text-white', false)
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
            ['name' => 'Проект Дзета', 'updated_at' => Carbon::parse('2026-01-30 10:00:00')],
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
            ->assertSee('min-w-full divide-y divide-gray-200', false)
            ->assertSee('Уроков:', false)
            ->assertSee('Длительность:', false)
            ->assertSee('Проекты')
            ->assertSeeInOrder([
                'Проект Дзета',
                'Проект Эпсилон',
                'Проект Дельта',
                'Проект Гамма',
                'Проект Бета',
            ])
            ->assertDontSee('Проект Альфа')
            ->assertDontSee('Архивный проект');

        foreach (array_slice(array_reverse($createdProjects), 0, 5) as $project) {
            $response->assertSee(route('projects.show', $project), false);
        }

        $response->assertDontSee(route('projects.show', $createdProjects[1]), false);
    }

    public function test_home_page_does_not_render_breadcrumbs(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertStatus(200)
            ->assertDontSee('data-breadcrumbs', false)
            ->assertDontSee('aria-label="Breadcrumb"', false);
    }

    public function test_home_page_hides_projects_from_hidden_folders_for_user_without_access(): void
    {
        $viewer = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_USER,
            'access_token' => (string) Str::uuid(),
        ]);

        $visibleFolder = Folder::query()->create([
            'name' => 'Публичная папка',
            'hidden' => false,
            'visible_for' => [],
        ]);
        $hiddenFolder = Folder::query()->create([
            'name' => 'Секретная папка',
            'hidden' => true,
            'visible_for' => [],
        ]);

        Project::query()->create([
            'folder_id' => $visibleFolder->id,
            'name' => 'Публичный проект',
            'tags' => null,
        ]);
        Project::query()->create([
            'folder_id' => $hiddenFolder->id,
            'name' => 'Секретный проект',
            'tags' => null,
        ]);

        $response = $this
            ->withCookie((string) config('simple_auth.cookie_name'), (string) $viewer->access_token)
            ->get(route('home'));

        $response
            ->assertStatus(200)
            ->assertSee('Публичный проект')
            ->assertDontSee('Секретный проект');
    }
}
