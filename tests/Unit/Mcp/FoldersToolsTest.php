<?php

namespace Tests\Unit\Mcp;

use App\Mcp\Servers\Video2BookServer;
use App\Mcp\Tools\Folders\CreateProjectFolderTool;
use App\Mcp\Tools\Folders\ListProjectFoldersTool;
use App\Models\Folder;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoldersToolsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_list_project_folders_tool_returns_only_visible_folders_with_projects_count(): void
    {
        $viewer = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_ADMIN,
        ]);
        $defaultFolder = Folder::query()->firstWhere('name', 'Проекты');

        $visibleFolder = Folder::query()->create([
            'name' => 'Видимая папка',
            'hidden' => false,
            'visible_for' => [],
        ]);
        $hiddenFolder = Folder::query()->create([
            'name' => 'Скрытая папка',
            'hidden' => true,
            'visible_for' => [],
        ]);

        Project::query()->create([
            'folder_id' => $visibleFolder->id,
            'name' => 'Проект 1',
            'tags' => null,
            'settings' => [],
        ]);
        Project::query()->create([
            'folder_id' => $visibleFolder->id,
            'name' => 'Проект 2',
            'tags' => null,
            'settings' => [],
        ]);
        Project::query()->create([
            'folder_id' => $hiddenFolder->id,
            'name' => 'Скрытый проект',
            'tags' => null,
            'settings' => [],
        ]);

        Video2BookServer::actingAs($viewer)
            ->tool(ListProjectFoldersTool::class)
            ->assertOk()
            ->assertStructuredContent([
                'folders' => [
                    [
                        'id' => $visibleFolder->id,
                        'name' => 'Видимая папка',
                        'hidden' => false,
                        'projects_count' => 2,
                    ],
                    [
                        'id' => $defaultFolder?->id,
                        'name' => 'Проекты',
                        'hidden' => false,
                        'projects_count' => 0,
                    ],
                ],
            ]);
    }

    public function test_create_project_folder_tool_creates_hidden_folder_with_locked_users(): void
    {
        $superAdmin = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_SUPERADMIN,
        ]);
        $viewer = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_ADMIN,
        ]);
        $selectedUser = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_USER,
        ]);

        Video2BookServer::actingAs($viewer)
            ->tool(CreateProjectFolderTool::class, [
                'name' => 'Новая скрытая папка',
                'hidden' => true,
                'visible_for_user_ids' => [$selectedUser->id],
            ])
            ->assertOk();

        $folder = Folder::query()->where('name', 'Новая скрытая папка')->firstOrFail();
        $expectedVisibleForUserIds = User::query()
            ->where('access_level', User::ACCESS_LEVEL_SUPERADMIN)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->push((int) $viewer->id)
            ->push((int) $selectedUser->id)
            ->unique()
            ->values()
            ->all();

        $this->assertTrue((bool) $folder->hidden);
        $this->assertEqualsCanonicalizing(
            $expectedVisibleForUserIds,
            array_map(static fn (mixed $id): int => (int) $id, (array) $folder->visible_for)
        );
    }
}
