<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Str;

class RotateUserInviteTokenAction
{
    public function handle(User $user): void
    {
        $user->forceFill([
            'access_token' => (string) Str::uuid(),
        ])->save();
    }
}
