<?php

namespace App\Livewire;

use App\Actions\User\CreateUserAction;
use App\Actions\User\DeleteUserAction;
use App\Actions\User\RotateUserInviteTokenAction;
use App\Actions\User\UpdateUserAction;
use App\Livewire\Concerns\AuthorizesAccessLevel;
use App\Models\User;
use App\Services\User\PaginatedUsersQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class UsersPage extends Component
{
    use AuthorizesAccessLevel;

    private const PER_PAGE = 15;

    public bool $showUserModal = false;

    public bool $showDeleteUserModal = false;

    public ?int $editingUserId = null;

    public ?int $deletingUserId = null;

    public string $userName = '';

    public string $userEmail = '';

    public int $userAccessLevel = User::ACCESS_LEVEL_USER;

    public function mount(): void
    {
        $this->authorizeAccessLevel(User::ACCESS_LEVEL_ADMIN);
    }

    public function openCreateUserModal(): void
    {
        $this->resetErrorBag();
        $this->editingUserId = null;
        $this->userName = '';
        $this->userEmail = '';
        $this->userAccessLevel = User::ACCESS_LEVEL_USER;
        $this->showUserModal = true;
    }

    public function openEditUserModal(int $userId): void
    {
        $this->resetErrorBag();

        $user = $this->findVisibleUser($userId);

        if ($user === null) {
            return;
        }

        $this->editingUserId = $user->id;
        $this->userName = (string) $user->name;
        $this->userEmail = (string) $user->email;
        $this->userAccessLevel = (int) $user->access_level;
        $this->showUserModal = true;

        if (! is_string($user->access_token) || $user->access_token === '') {
            app(RotateUserInviteTokenAction::class)->handle($user);
        }
    }

    public function closeUserModal(): void
    {
        $this->showUserModal = false;
    }

    public function saveUser(CreateUserAction $createUserAction, UpdateUserAction $updateUserAction): void
    {
        $editingUser = $this->editingUserId === null
            ? null
            : $this->findVisibleUser($this->editingUserId);

        if ($this->editingUserId !== null && $editingUser === null) {
            return;
        }

        $validated = validator([
            'userName' => trim($this->userName),
            'userEmail' => mb_strtolower(trim($this->userEmail)),
            'userAccessLevel' => $this->userAccessLevel,
        ], [
            'userName' => ['required', 'string', 'max:255'],
            'userEmail' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->editingUserId),
            ],
            'userAccessLevel' => ['required', 'integer', Rule::in($this->editableAccessLevels($editingUser))],
        ], [], [
            'userName' => 'имя пользователя',
            'userEmail' => 'email пользователя',
            'userAccessLevel' => 'уровень доступа',
        ])->validate();

        if ($this->editingUserId === null) {
            $user = $createUserAction->handle(
                name: $validated['userName'],
                email: $validated['userEmail'],
                accessLevel: (int) $validated['userAccessLevel'],
            );

            $this->editingUserId = $user->id;
        } else {
            $user = $editingUser;
            $accessLevel = $user->isSuperAdmin()
                ? User::ACCESS_LEVEL_SUPERADMIN
                : (int) $validated['userAccessLevel'];

            $updateUserAction->handle(
                user: $user,
                name: $validated['userName'],
                email: $validated['userEmail'],
                accessLevel: $accessLevel,
            );
        }

        $this->closeUserModal();
    }

    public function setUserAccessLevel(int $accessLevel): void
    {
        if (! in_array($accessLevel, [User::ACCESS_LEVEL_USER, User::ACCESS_LEVEL_ADMIN], true)) {
            return;
        }

        if (! $this->canEditSelectedUserAccessLevel) {
            return;
        }

        $this->userAccessLevel = $accessLevel;
    }

    public function openDeleteUserModal(int $userId): void
    {
        $user = $this->findVisibleUser($userId);

        if ($user === null) {
            return;
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        $this->deletingUserId = $user->id;
        $this->showDeleteUserModal = true;
    }

    public function closeDeleteUserModal(): void
    {
        $this->showDeleteUserModal = false;
        $this->deletingUserId = null;
    }

    public function deleteUser(DeleteUserAction $deleteUserAction): void
    {
        if ($this->deletingUserId === null) {
            return;
        }

        $user = $this->findVisibleUser($this->deletingUserId);

        if ($user === null) {
            $this->closeDeleteUserModal();

            return;
        }

        if ($user->isSuperAdmin()) {
            $this->closeDeleteUserModal();

            return;
        }

        $deleteUserAction->handle($user);

        $this->closeDeleteUserModal();
    }

    public function rotateInviteToken(RotateUserInviteTokenAction $rotateUserInviteTokenAction): void
    {
        if ($this->editingUserId === null) {
            return;
        }

        $user = $this->findVisibleUser($this->editingUserId);

        if ($user === null) {
            return;
        }

        $rotateUserInviteTokenAction->handle($user);
    }

    public function canDelete(User $user): bool
    {
        return ! $user->isSuperAdmin();
    }

    public function levelLabel(int $accessLevel): string
    {
        return match ($accessLevel) {
            User::ACCESS_LEVEL_SUPERADMIN => 'Суперадмин',
            User::ACCESS_LEVEL_ADMIN => 'Админ',
            default => 'Пользователь',
        };
    }

    public function getEditingUserInviteLinkProperty(): ?string
    {
        if ($this->editingUserId === null) {
            return null;
        }

        $user = $this->findVisibleUser($this->editingUserId);

        if ($user === null) {
            return null;
        }

        return $this->inviteLink($user);
    }

    public function getDeletingUserNameProperty(): string
    {
        if ($this->deletingUserId === null) {
            return '';
        }

        $user = $this->findVisibleUser($this->deletingUserId);

        if ($user === null) {
            return '';
        }

        return (string) $user->name;
    }

    public function getCanEditSelectedUserAccessLevelProperty(): bool
    {
        if ($this->editingUserId === null) {
            return true;
        }

        $user = $this->findVisibleUser($this->editingUserId);

        if ($user === null) {
            return false;
        }

        return ! $user->isSuperAdmin();
    }

    public function render(): View
    {
        return view('pages.users-page', [
            'users' => app(PaginatedUsersQuery::class)->getVisibleFor($this->viewer(), self::PER_PAGE),
        ])->layout('layouts.app', [
            'title' => 'Пользователи | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [
                ['label' => 'Пользователи', 'current' => true],
            ],
        ]);
    }

    private function inviteLink(User $user): string
    {
        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');

        return "{$baseUrl}/invite/{$user->access_token}";
    }

    private function findVisibleUser(int $userId): ?User
    {
        return $this->visibleUsersQuery()
            ->whereKey($userId)
            ->first();
    }

    private function visibleUsersQuery(): Builder
    {
        $viewer = $this->viewer();

        return User::query()
            ->when(
                $viewer->isSuperAdmin(),
                fn (Builder $query) => $query->where(function (Builder $innerQuery) use ($viewer): void {
                    $innerQuery
                        ->where('access_level', '<', User::ACCESS_LEVEL_SUPERADMIN)
                        ->orWhere('id', $viewer->id);
                }),
                fn (Builder $query) => $query->where('access_level', '<', User::ACCESS_LEVEL_SUPERADMIN)
            );
    }

    /**
     * @return list<int>
     */
    private function editableAccessLevels(?User $editingUser): array
    {
        $levels = [
            User::ACCESS_LEVEL_USER,
            User::ACCESS_LEVEL_ADMIN,
        ];

        if ($editingUser?->isSuperAdmin()) {
            $levels[] = User::ACCESS_LEVEL_SUPERADMIN;
        }

        return $levels;
    }

    private function viewer(): User
    {
        $authUser = auth()->user();

        if ($authUser instanceof User) {
            return $authUser;
        }

        $token = request()->cookie((string) config('simple_auth.cookie_name', 'video2book_access_token'));

        if (is_string($token) && $token !== '') {
            $user = User::query()
                ->where('access_token', $token)
                ->first();

            if ($user instanceof User) {
                Auth::guard('web')->setUser($user);

                return $user;
            }
        }

        abort(403, 'Доступ закрыт.');
    }
}
