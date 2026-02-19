<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GenerateInviteCommandTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_artisan_command_generates_new_access_token_and_prints_invite_link(): void
    {
        $user = User::query()->where('email', config('simple_auth.email'))->firstOrFail();

        $this->artisan('auth:generate-invite')
            ->expectsOutputToContain('/invite/')
            ->assertSuccessful();

        $user->refresh();

        $this->assertNotNull($user->access_token);
        $this->assertStringContainsString('-', (string) $user->access_token);
    }

    public function test_artisan_command_creates_team_user_when_missing_and_prints_invite_link(): void
    {
        User::query()->delete();

        $this->artisan('auth:generate-invite')
            ->expectsOutputToContain('/invite/')
            ->assertSuccessful();

        $user = User::query()->where('email', config('simple_auth.email'))->first();

        $this->assertNotNull($user);
        $this->assertNotNull($user?->access_token);
    }

    public function test_show_invite_command_prints_existing_invite_link_without_token_rotation(): void
    {
        $user = User::query()->where('email', config('simple_auth.email'))->firstOrFail();
        $token = (string) Str::uuid();

        $user->forceFill([
            'access_token' => $token,
        ])->save();

        $this->artisan('auth:show-invite')
            ->expectsOutputToContain("/invite/{$token}")
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame($token, $user->access_token);
    }

    public function test_show_invite_command_creates_user_and_token_when_missing(): void
    {
        User::query()->delete();

        $this->artisan('auth:show-invite')
            ->expectsOutputToContain('/invite/')
            ->assertSuccessful();

        $user = User::query()->where('email', config('simple_auth.email'))->first();

        $this->assertNotNull($user);
        $this->assertNotNull($user?->access_token);
    }
}
