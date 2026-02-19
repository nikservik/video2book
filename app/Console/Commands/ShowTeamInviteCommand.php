<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ShowTeamInviteCommand extends Command
{
    protected $signature = 'auth:show-invite';

    protected $description = 'Show current invite link for the single team user';

    public function handle(): int
    {
        $email = (string) config('simple_auth.email', 'team@local');

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Team',
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(64)),
                'remember_token' => Str::random(10),
            ]
        );

        if (! is_string($user->access_token) || $user->access_token === '') {
            $user->forceFill([
                'access_token' => (string) Str::uuid(),
            ])->save();
        }

        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');
        $inviteLink = "{$baseUrl}/invite/{$user->access_token}";

        $this->line($inviteLink);

        return self::SUCCESS;
    }
}
