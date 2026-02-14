<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Project;
use App\Models\ProjectTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProjectsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_page_shows_projects_sorted_by_last_update_with_lessons_count(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $this->createProject('Старый проект', Carbon::parse('2026-01-10 10:00:00'), 1);
        $this->createProject('Средний проект', Carbon::parse('2026-01-15 10:00:00'), 3);
        $this->createProject('Новый проект', Carbon::parse('2026-01-20 10:00:00'), 0);

        $response = $this->get(route('projects.index'));

        $response
            ->assertStatus(200)
            ->assertSeeInOrder([
                'Новый проект',
                'Средний проект',
                'Старый проект',
            ])
            ->assertSee('Уроков: 3')
            ->assertSee('Уроков: 1')
            ->assertSee('Уроков: 0');
    }

    public function test_projects_page_has_pagination(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        for ($index = 1; $index <= 16; $index++) {
            $name = sprintf('PRJ-%02d', $index);
            $updatedAt = Carbon::parse('2026-01-01 00:00:00')->addMinutes($index);
            $this->createProject($name, $updatedAt, 0);
        }

        $pageOne = $this->get(route('projects.index'));

        $pageOne
            ->assertStatus(200)
            ->assertSee('PRJ-16')
            ->assertSee('PRJ-02')
            ->assertDontSee('PRJ-01')
            ->assertSee('?page=2', false);

        $pageTwo = $this->get(route('projects.index', ['page' => 2]));

        $pageTwo
            ->assertStatus(200)
            ->assertSee('PRJ-01')
            ->assertDontSee('PRJ-16');
    }

    public function test_projects_page_cards_link_to_project_show_page(): void
    {
        $project = Project::query()->create([
            'name' => 'Проект со ссылкой',
            'tags' => null,
        ]);

        $response = $this->get(route('projects.index'));

        $response
            ->assertStatus(200)
            ->assertSee(route('projects.show', $project), false);
    }

    private function createProject(string $name, Carbon $updatedAt, int $lessonsCount): void
    {
        $project = Project::query()->create([
            'name' => $name,
            'tags' => null,
        ]);

        $project->timestamps = false;
        $project->forceFill([
            'created_at' => $updatedAt->copy()->subDay(),
            'updated_at' => $updatedAt,
        ])->saveQuietly();

        for ($index = 1; $index <= $lessonsCount; $index++) {
            Lesson::query()->create([
                'project_id' => $project->id,
                'name' => "{$name} Lesson {$index}",
                'tag' => 'default',
                'source_filename' => null,
                'settings' => [],
            ]);
        }
    }
}
