<?php

namespace Tests\Feature;

use App\Livewire\UsersPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class UsersPageTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_users_page_renders_for_admin_with_add_button(): void
    {
        $admin = $this->makeUser('Admin', 'admin@local', User::ACCESS_LEVEL_ADMIN);
        $user = $this->makeUser('Student', 'student@local', User::ACCESS_LEVEL_USER);

        $response = $this
            ->withCookie((string) config('simple_auth.cookie_name'), (string) $admin->access_token)
            ->get(route('users.index'));

        $response
            ->assertStatus(200)
            ->assertSee('Пользователи')
            ->assertSee('Добавить пользователя')
            ->assertSee($user->email)
            ->assertDontSee('team@local');
    }

    public function test_superadmin_sees_own_superadmin_card(): void
    {
        $superAdmin = User::query()->where('email', config('simple_auth.email'))->firstOrFail();
        $superAdmin->forceFill([
            'name' => 'Team',
            'access_level' => User::ACCESS_LEVEL_SUPERADMIN,
            'access_token' => (string) Str::uuid(),
        ])->save();

        $response = $this
            ->withCookie((string) config('simple_auth.cookie_name'), (string) $superAdmin->access_token)
            ->get(route('users.index'));

        $response
            ->assertStatus(200)
            ->assertSee('team@local')
            ->assertSee('Суперадмин');
    }

    public function test_admin_cannot_open_superadmin_modal_directly(): void
    {
        $admin = $this->makeUser('Admin', 'admin@local', User::ACCESS_LEVEL_ADMIN);
        $superAdmin = User::query()->where('email', config('simple_auth.email'))->firstOrFail();
        $superAdmin->forceFill([
            'name' => 'Team',
            'access_level' => User::ACCESS_LEVEL_SUPERADMIN,
            'access_token' => (string) Str::uuid(),
        ])->save();

        Livewire::actingAs($admin)
            ->test(UsersPage::class)
            ->call('openEditUserModal', $superAdmin->id)
            ->assertSet('showUserModal', false)
            ->assertSet('editingUserId', null);
    }

    public function test_users_page_can_create_user(): void
    {
        $admin = $this->makeUser('Admin', 'admin@local', User::ACCESS_LEVEL_ADMIN);

        Livewire::actingAs($admin)
            ->test(UsersPage::class)
            ->call('openCreateUserModal')
            ->set('userName', 'Новый пользователь')
            ->set('userEmail', 'new-user@example.com')
            ->call('saveUser')
            ->assertSet('showUserModal', false);

        $this->assertDatabaseHas('users', [
            'name' => 'Новый пользователь',
            'email' => 'new-user@example.com',
            'access_level' => User::ACCESS_LEVEL_USER,
        ]);

        $newUser = User::query()->where('email', 'new-user@example.com')->firstOrFail();
        $this->assertNotNull($newUser->access_token);
    }

    public function test_users_page_can_update_user_and_rotate_token(): void
    {
        $admin = $this->makeUser('Admin', 'admin@local', User::ACCESS_LEVEL_ADMIN);
        $user = $this->makeUser('Old Name', 'old@local', User::ACCESS_LEVEL_USER);
        $oldToken = (string) $user->access_token;

        Livewire::actingAs($admin)
            ->test(UsersPage::class)
            ->call('openEditUserModal', $user->id)
            ->set('userName', 'New Name')
            ->set('userEmail', 'new@example.com')
            ->call('saveUser')
            ->call('openEditUserModal', $user->id)
            ->call('rotateInviteToken');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $user->refresh();
        $this->assertNotSame($oldToken, (string) $user->access_token);
    }

    public function test_users_page_can_delete_regular_user(): void
    {
        $admin = $this->makeUser('Admin', 'admin@local', User::ACCESS_LEVEL_ADMIN);
        $user = $this->makeUser('Delete Me', 'delete@local', User::ACCESS_LEVEL_USER);

        Livewire::actingAs($admin)
            ->test(UsersPage::class)
            ->call('openDeleteUserModal', $user->id)
            ->assertSet('showDeleteUserModal', true)
            ->call('deleteUser')
            ->assertSet('showDeleteUserModal', false);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    private function makeUser(string $name, string $email, int $accessLevel): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'access_level' => $accessLevel,
            'access_token' => (string) Str::uuid(),
        ]);
    }
}
