<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
