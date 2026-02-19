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

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Team',
                'password' => Str::random(64),
            ]
        );

        if (! is_string($user->access_token) || $user->access_token === '') {
            $user->forceFill([
                'access_token' => (string) Str::uuid(),
            ])->save();
        }

        $this->withCookie($cookieName, (string) $user->access_token);
    }
}
