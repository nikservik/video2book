<?php

namespace Tests\Unit\Mcp\Folders;

use App\Mcp\Servers\Video2BookServer;
use App\Mcp\Tools\Folders\CreateProjectFolderTool;
use App\Mcp\Tools\Folders\ListProjectFoldersTool;
use App\Models\Folder;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FoldersToolsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_list_project_folders_tool_returns_only_folders_visible_to_user(): void
    {
        $viewer = $this->makeUser();

        $publicFolder = Folder::query()->create([
            'name' => 'Public',
            'hidden' => false,
            'visible_for' => [],
        ]);
        $hiddenVisibleFolder = Folder::query()->create([
            'name' => 'Visible Hidden',
            'hidden' => true,
            'visible_for' => [$viewer->id],
        ]);
        Folder::query()->create([
            'name' => 'Private Hidden',
            'hidden' => true,
            'visible_for' => [],
        ]);

        Project::query()->create(['folder_id' => $publicFolder->id, 'name' => 'Project A', 'tags' => null]);
        Project::query()->create(['folder_id' => $hiddenVisibleFolder->id, 'name' => 'Project B', 'tags' => null]);

        Video2BookServer::actingAs($viewer)
            ->tool(ListProjectFoldersTool::class)
            ->assertOk()
            ->assertAuthenticatedAs($viewer)
            ->assertSee(['Public', 'Visible Hidden'])
            ->assertDontSee('Private Hidden');
    }

    public function test_create_project_folder_tool_includes_current_user_and_superadmins_for_hidden_folder(): void
    {
        $viewer = $this->makeUser();
        $superAdmin = $this->makeUser(accessLevel: User::ACCESS_LEVEL_SUPERADMIN);
        $extraUser = $this->makeUser();

        $response = Video2BookServer::actingAs($viewer)
            ->tool(CreateProjectFolderTool::class, [
                'name' => 'Secret Folder',
                'hidden' => true,
                'visible_for_user_ids' => [$extraUser->id],
            ])
            ->assertOk()
            ->assertAuthenticatedAs($viewer);

        $folder = Folder::query()->firstWhere('name', 'Secret Folder');

        $this->assertNotNull($folder);

        $response->assertSee('Secret Folder');

        $this->assertSame('Secret Folder', $folder->name);
        $this->assertTrue($folder->hidden);
        $this->assertContains($extraUser->id, $folder->visible_for);
        $this->assertContains($viewer->id, $folder->visible_for);
        $this->assertContains($superAdmin->id, $folder->visible_for);
    }

    private function makeUser(int $accessLevel = User::ACCESS_LEVEL_ADMIN): User
    {
        return User::factory()->create([
            'access_token' => (string) Str::uuid(),
            'access_level' => $accessLevel,
        ]);
    }
}
