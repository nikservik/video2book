<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateTeamInviteCommand extends Command
{
    protected $signature = 'auth:generate-invite';

    protected $description = 'Generate invite token for the single team user';

    public function handle(): int
    {
        $email = (string) config('simple_auth.email', 'team@local');

        $user = User::query()
            ->where('email', $email)
            ->first();

        if ($user === null) {
            $this->error("User {$email} not found. Run migrations first.");

            return self::FAILURE;
        }

        $token = (string) Str::uuid();

        $user->forceFill([
            'access_token' => $token,
        ])->save();

        $inviteLink = route('invites.accept', ['token' => $token], absolute: true);

        $this->line($inviteLink);

        return self::SUCCESS;
    }
}
