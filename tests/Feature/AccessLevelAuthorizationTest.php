<?php

namespace Tests\Feature;

use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessLevelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_navigation_for_regular_user_contains_only_home_and_projects(): void
    {
        $user = $this->makeUserWithAccessLevel(User::ACCESS_LEVEL_USER);

        $response = $this
            ->withCookie((string) config('simple_auth.cookie_name'), (string) $user->access_token)
            ->get(route('home'));

        $response
            ->assertStatus(200)
            ->assertSee('data-menu-item="home"', false)
            ->assertSee('data-menu-item="projects"', false)
            ->assertDontSee('data-menu-item="pipelines"', false)
            ->assertDontSee('data-menu-item="users"', false);
    }

    public function test_navigation_for_admin_contains_pipelines_and_users(): void
    {
        $admin = $this->makeUserWithAccessLevel(User::ACCESS_LEVEL_ADMIN);

        $response = $this
            ->withCookie((string) config('simple_auth.cookie_name'), (string) $admin->access_token)
            ->get(route('home'));

        $response
            ->assertStatus(200)
            ->assertSee('data-menu-item="home"', false)
            ->assertSee('data-menu-item="projects"', false)
            ->assertSee('data-menu-item="pipelines"', false)
            ->assertSee('data-menu-item="users"', false);
    }

    public function test_regular_user_cannot_access_pipelines_and_users_pages(): void
    {
        $user = $this->makeUserWithAccessLevel(User::ACCESS_LEVEL_USER);
        $cookieName = (string) config('simple_auth.cookie_name');

        $pipeline = Pipeline::query()->create();
        $version = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Ограниченный пайплайн',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);
        $pipeline->update(['current_version_id' => $version->id]);

        $this->withCookie($cookieName, (string) $user->access_token)
            ->get(route('pipelines.index'))
            ->assertStatus(403)
            ->assertSee('Доступ закрыт');

        $this->withCookie($cookieName, (string) $user->access_token)
            ->get(route('pipelines.show', $pipeline))
            ->assertStatus(403)
            ->assertSee('Доступ закрыт');

        $this->withCookie($cookieName, (string) $user->access_token)
            ->get(route('users.index'))
            ->assertStatus(403)
            ->assertSee('Доступ закрыт');
    }

    public function test_admin_can_access_pipelines_and_users_pages(): void
    {
        $admin = $this->makeUserWithAccessLevel(User::ACCESS_LEVEL_ADMIN);
        $cookieName = (string) config('simple_auth.cookie_name');

        $pipeline = Pipeline::query()->create();
        $version = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Доступный пайплайн',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);
        $pipeline->update(['current_version_id' => $version->id]);

        $this->withCookie($cookieName, (string) $admin->access_token)
            ->get(route('pipelines.index'))
            ->assertStatus(200)
            ->assertSee('Пайплайны');

        $this->withCookie($cookieName, (string) $admin->access_token)
            ->get(route('pipelines.show', $pipeline))
            ->assertStatus(200)
            ->assertSee('Доступный пайплайн');

        $this->withCookie($cookieName, (string) $admin->access_token)
            ->get(route('users.index'))
            ->assertStatus(200)
            ->assertSee('Пользователи');
    }

    private function makeUserWithAccessLevel(int $accessLevel): User
    {
        return User::factory()->create([
            'access_token' => (string) Str::uuid(),
            'access_level' => $accessLevel,
        ]);
    }
}
