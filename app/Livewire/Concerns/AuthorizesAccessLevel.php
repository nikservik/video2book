<?php

namespace App\Livewire\Concerns;

use App\Models\User;

trait AuthorizesAccessLevel
{
    protected function authorizeAccessLevel(int $requiredAccessLevel): void
    {
        $user = auth()->user();

        abort_unless(
            $user instanceof User && $user->canAccessLevel($requiredAccessLevel),
            403,
            'Доступ закрыт.'
        );
    }
}
