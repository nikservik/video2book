<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected bool $withTeamAuthCookie = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootTeamAuthCookie();
    }

    private function bootTeamAuthCookie(): void
    {
        if ($this->withTeamAuthCookie === false) {
            return;
        }

        try {
            if (! Schema::hasTable('users')) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        $email = (string) config('simple_auth.email', 'team@local');
        $cookieName = (string) config('simple_auth.cookie_name', 'video2book_access_token');

        $user = User::withTrashed()->firstWhere('email', $email);

        if ($user === null) {
            $user = User::query()->create([
                'name' => 'Team',
                'email' => $email,
                'password' => Str::random(64),
                'access_level' => User::ACCESS_LEVEL_SUPERADMIN,
            ]);
        } elseif ($user->trashed()) {
            $user->restore();
        }

        if (! is_string($user->access_token) || $user->access_token === '' || ! $user->isSuperAdmin()) {
            $user->forceFill([
                'access_token' => (string) Str::uuid(),
                'access_level' => User::ACCESS_LEVEL_SUPERADMIN,
            ])->save();
        }

        $this->actingAs($user);
        $this->withCookie($cookieName, (string) $user->access_token);
    }
}
