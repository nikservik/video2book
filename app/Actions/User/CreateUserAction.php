<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateUserAction
{
    public function handle(string $name, string $email, int $accessLevel = User::ACCESS_LEVEL_USER): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make(Str::random(64)),
            'remember_token' => Str::random(10),
            'access_token' => (string) Str::uuid(),
            'access_level' => $accessLevel,
        ]);
    }
}
