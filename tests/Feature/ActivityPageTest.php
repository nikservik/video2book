<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Models\Project;
use App\Models\ProjectTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityPageTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_activity_page_shows_formatted_activity_row(): void
    {
        Carbon::setTestNow('2026-02-24 15:40:00');

        try {
            $admin = $this->makeAdmin('Админ Тест', 'admin-activity@local');
            $cookieName = (string) config('simple_auth.cookie_name');

            $this->actingAs($admin);

            Project::query()->create([
                'name' => 'Новый проект',
                'tags' => null,
            ]);

            $response = $this
                ->withCookie($cookieName, (string) $admin->access_token)
                ->get(route('activity.index'));

            $response
                ->assertStatus(200)
                ->assertSee('24.02.2026 15:40')
                ->assertSee('Админ Тест')
                ->assertSee('добавил(а)')
                ->assertSee('проект')
                ->assertSee('«Новый проект»');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_activity_page_paginates_by_twenty_rows(): void
    {
        $admin = $this->makeAdmin('Админ Пагинация', 'admin-pagination@local');
        $cookieName = (string) config('simple_auth.cookie_name');

        $this->actingAs($admin);

        for ($index = 1; $index <= 21; $index++) {
            Project::query()->create([
                'name' => sprintf('Проект %02d', $index),
                'tags' => null,
            ]);
        }

        $firstPageResponse = $this
            ->withCookie($cookieName, (string) $admin->access_token)
            ->get(route('activity.index'));

        $firstPageResponse
            ->assertStatus(200)
            ->assertSee('Проект 21')
            ->assertSee('Проект 02')
            ->assertDontSee('Проект 01');

        $secondPageResponse = $this
            ->withCookie($cookieName, (string) $admin->access_token)
            ->get(route('activity.index', ['page' => 2]));

        $secondPageResponse
            ->assertStatus(200)
            ->assertSee('Проект 01');
    }

    public function test_activity_page_uses_lesson_and_pipeline_version_for_pipeline_run_name(): void
    {
        $admin = $this->makeAdmin('Админ Прогоны', 'admin-runs@local');
        $cookieName = (string) config('simple_auth.cookie_name');

        $this->actingAs($admin);

        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект активности прогонов',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок 5',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        $pipeline = Pipeline::query()->create();
        $pipelineVersion = $pipeline->versions()->create([
            'version' => 3,
            'title' => 'Базовый пайплайн',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);

        PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'queued',
            'state' => [],
        ]);

        $response = $this
            ->withCookie($cookieName, (string) $admin->access_token)
            ->get(route('activity.index'));

        $response
            ->assertStatus(200)
            ->assertSee('прогон')
            ->assertSee('«Урок 5 — Базовый пайплайн • v3»');
    }

    public function test_activity_page_appends_project_name_for_lesson_rows(): void
    {
        $admin = $this->makeAdmin('Админ Уроки', 'admin-lessons@local');
        $cookieName = (string) config('simple_auth.cookie_name');

        $this->actingAs($admin);

        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект А',
            'tags' => null,
        ]);

        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок А1',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        $response = $this
            ->withCookie($cookieName, (string) $admin->access_token)
            ->get(route('activity.index'));

        $response
            ->assertStatus(200)
            ->assertSee('урок')
            ->assertSee('«Урок А1» в проекте «Проект А»');
    }

    public function test_activity_page_uses_project_name_for_updated_project_without_name_in_activity_properties(): void
    {
        $admin = $this->makeAdmin('Суперадмин', 'superadmin-project-updated@local');
        $cookieName = (string) config('simple_auth.cookie_name');

        $this->actingAs($admin);

        $project = Project::query()->create([
            'name' => 'Проект для активности',
            'tags' => null,
        ]);

        $project->update([
            'tags' => 'new-tags',
        ]);

        $updatedActivity = Activity::query()
            ->where('event', 'updated')
            ->where('subject_type', Project::class)
            ->where('subject_id', $project->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($updatedActivity);
        $this->assertArrayNotHasKey('name', $updatedActivity->properties['attributes'] ?? []);
        $this->assertArrayNotHasKey('name', $updatedActivity->properties['old'] ?? []);

        $response = $this
            ->withCookie($cookieName, (string) $admin->access_token)
            ->get(route('activity.index'));

        $response
            ->assertStatus(200)
            ->assertSee('Суперадмин')
            ->assertDontSee("Проект #{$project->id}");

        $this->assertMatchesRegularExpression(
            '/Суперадмин\s+—\s+изменил\(а\)\s+проект\s+«Проект для активности»/u',
            $response->getContent()
        );
    }

    public function test_activity_page_shows_custom_description_for_pipeline_run_step_result_edit(): void
    {
        $admin = $this->makeAdmin('Редактор', 'admin-step-edit@local');
        $cookieName = (string) config('simple_auth.cookie_name');

        $this->actingAs($admin);

        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект Б',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок Б1',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        $pipeline = Pipeline::query()->create();
        $pipelineVersion = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Шаблон Б',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);

        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'queued',
            'state' => [],
        ]);

        Activity::query()->delete();

        $description = 'Редактор изменил текст в шаге 2 в уроке «Урок Б1» проекта «Проект Б»';

        activity('pipeline-runs')
            ->performedOn($run)
            ->causedBy($admin)
            ->event('updated')
            ->withProperties(['context' => 'pipeline-run-step-result-edited'])
            ->log($description);

        $response = $this
            ->withCookie($cookieName, (string) $admin->access_token)
            ->get(route('activity.index'));

        $response
            ->assertStatus(200)
            ->assertSee($description)
            ->assertDontSee('изменил(а) прогон');
    }

    public function test_activity_page_shows_custom_description_for_pipeline_run_step_result_restore(): void
    {
        $admin = $this->makeAdmin('Редактор', 'admin-step-restore@local');
        $cookieName = (string) config('simple_auth.cookie_name');

        $this->actingAs($admin);

        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект В',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок В1',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        $pipeline = Pipeline::query()->create();
        $pipelineVersion = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Шаблон В',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);

        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'queued',
            'state' => [],
        ]);

        Activity::query()->delete();

        $description = 'Редактор восстановил текст в шаге 2 в уроке «Урок В1» проекта «Проект В»';

        activity('pipeline-runs')
            ->performedOn($run)
            ->causedBy($admin)
            ->event('updated')
            ->withProperties(['context' => 'pipeline-run-step-result-restored'])
            ->log($description);

        $response = $this
            ->withCookie($cookieName, (string) $admin->access_token)
            ->get(route('activity.index'));

        $response
            ->assertStatus(200)
            ->assertSee($description)
            ->assertDontSee('изменил(а) прогон');
    }

    private function makeAdmin(string $name, string $email): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'access_token' => (string) Str::uuid(),
            'access_level' => User::ACCESS_LEVEL_ADMIN,
        ]);
    }
}
