<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GenerateTeamInviteCommand extends Command
{
    protected $signature = 'auth:generate-invite';

    protected $description = 'Generate invite token for the single team user';

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

        $token = (string) Str::uuid();

        $user->forceFill([
            'access_token' => $token,
        ])->save();

        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');
        $inviteLink = "{$baseUrl}/invite/{$token}";

        $this->line($inviteLink);

        return self::SUCCESS;
    }
}
