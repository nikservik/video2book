<?php

namespace Tests\Unit\Mcp;

use App\Mcp\Servers\Video2BookServer;
use App\Mcp\Tools\Folders\CreateProjectFolderTool;
use App\Mcp\Tools\Folders\ListProjectFoldersTool;
use App\Models\Folder;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Testing\TestResponse;
use ReflectionProperty;
use Tests\TestCase;

class FolderToolsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_list_project_folders_tool_returns_only_visible_folders_with_project_counts(): void
    {
        $viewer = User::factory()->create([
            'access_token' => (string) Str::uuid(),
        ]);
        $otherUser = User::factory()->create();

        $publicFolder = Folder::query()->create([
            'name' => 'Alpha',
            'hidden' => false,
            'visible_for' => [],
        ]);
        $hiddenVisibleFolder = Folder::query()->create([
            'name' => 'Beta',
            'hidden' => true,
            'visible_for' => [$viewer->id],
        ]);
        Folder::query()->create([
            'name' => 'Gamma',
            'hidden' => true,
            'visible_for' => [$otherUser->id],
        ]);

        Project::query()->create([
            'folder_id' => $publicFolder->id,
            'name' => 'Public project',
        ]);
        Project::query()->create([
            'folder_id' => $hiddenVisibleFolder->id,
            'name' => 'Hidden visible project',
        ]);
        Project::query()->create([
            'folder_id' => $hiddenVisibleFolder->id,
            'name' => 'Hidden visible project 2',
        ]);

        $content = $this->structuredContent(
            Video2BookServer::actingAs($viewer)->tool(ListProjectFoldersTool::class)
        );

        $folders = collect($content['folders'])
            ->whereIn('name', ['Alpha', 'Beta'])
            ->values()
            ->all();

        $this->assertSame([
            [
                'id' => $publicFolder->id,
                'name' => 'Alpha',
                'hidden' => false,
                'projects_count' => 1,
                'visible_for_user_ids' => [],
            ],
            [
                'id' => $hiddenVisibleFolder->id,
                'name' => 'Beta',
                'hidden' => true,
                'projects_count' => 2,
                'visible_for_user_ids' => [$viewer->id],
            ],
        ], $folders);
    }

    public function test_create_project_folder_tool_adds_current_user_and_superadmins_to_hidden_folder_visibility(): void
    {
        $viewer = User::factory()->create([
            'access_token' => (string) Str::uuid(),
            'access_level' => User::ACCESS_LEVEL_ADMIN,
        ]);
        $superAdmin = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_SUPERADMIN,
        ]);
        $extraUser = User::factory()->create();

        $content = $this->structuredContent(
            Video2BookServer::actingAs($viewer)->tool(CreateProjectFolderTool::class, [
                'name' => 'Secret folder',
                'hidden' => true,
                'visible_for_user_ids' => [$extraUser->id],
            ])
        );

        $folder = Folder::query()->firstWhere('name', 'Secret folder');

        $this->assertNotNull($folder);
        $this->assertTrue((bool) $folder->hidden);

        $expectedVisibleFor = User::query()
            ->where('access_level', User::ACCESS_LEVEL_SUPERADMIN)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->push((int) $viewer->id)
            ->push((int) $extraUser->id)
            ->unique()
            ->all();
        sort($expectedVisibleFor);

        $actualVisibleFor = collect($folder->visible_for ?? [])
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        sort($actualVisibleFor);

        $responseVisibleFor = $content['folder']['visible_for_user_ids'];
        sort($responseVisibleFor);

        $this->assertSame($expectedVisibleFor, $actualVisibleFor);
        $this->assertSame($expectedVisibleFor, $responseVisibleFor);
        $this->assertSame('Secret folder', $content['folder']['name']);
        $this->assertTrue($content['folder']['hidden']);
        $this->assertSame(0, $content['folder']['projects_count']);
    }

    /**
     * @return array<string, mixed>
     */
    private function structuredContent(TestResponse $response): array
    {
        $property = new ReflectionProperty($response, 'response');
        $property->setAccessible(true);
        $jsonRpcResponse = $property->getValue($response);

        return $jsonRpcResponse->toArray()['result']['structuredContent'] ?? [];
    }
}
