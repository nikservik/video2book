<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InviteAuthTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_middleware_blocks_home_when_cookie_is_missing(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertStatus(403)
            ->assertSee('Доступ закрыт')
            ->assertDontSee('Главная');
    }

    public function test_middleware_allows_home_when_cookie_token_is_valid(): void
    {
        $user = User::query()->where('email', config('simple_auth.email'))->firstOrFail();
        $token = (string) Str::uuid();

        $user->forceFill([
            'access_token' => $token,
        ])->save();

        $response = $this
            ->withCookie((string) config('simple_auth.cookie_name'), $token)
            ->get(route('home'));

        $response
            ->assertStatus(200)
            ->assertSee('Главная');
    }

    public function test_invite_route_sets_cookie_and_redirects_to_home_for_valid_token(): void
    {
        $user = User::query()->where('email', config('simple_auth.email'))->firstOrFail();
        $token = (string) Str::uuid();

        $user->forceFill([
            'access_token' => $token,
        ])->save();

        $response = $this->get(route('invites.accept', ['token' => $token]));

        $response
            ->assertRedirect(route('home'))
            ->assertCookie((string) config('simple_auth.cookie_name'), $token);
    }

    public function test_invite_route_returns_access_closed_for_invalid_token(): void
    {
        $response = $this->get(route('invites.accept', ['token' => 'invalid-token']));

        $response
            ->assertStatus(403)
            ->assertSee('Доступ закрыт');
    }
}
