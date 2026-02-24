<?php

namespace App\Actions\User;

use App\Models\User;

class UpdateUserAction
{
    public function handle(User $user, string $name, string $email): void
    {
        $user->update([
            'name' => $name,
            'email' => $email,
        ]);
    }
}
