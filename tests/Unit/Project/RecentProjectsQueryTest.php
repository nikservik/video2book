<?php

namespace Tests\Unit\Project;

use App\Models\Project;
use App\Services\Project\RecentProjectsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecentProjectsQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_latest_projects_limited_to_five(): void
    {
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
        }

        $result = app(RecentProjectsQuery::class)->get();

        $this->assertCount(5, $result);
        $this->assertSame([
            'Проект Эпсилон',
            'Проект Дельта',
            'Проект Гамма',
            'Проект Бета',
            'Проект Альфа',
        ], $result->pluck('name')->all());
    }
}
