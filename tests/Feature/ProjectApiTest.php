<?php

namespace Tests\Feature;

use App\Mcp\Support\McpPresenter;
use App\Models\Folder;
use App\Models\Project;
use App\Models\User;
use App\Services\Project\ProjectFoldersQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_api_requires_valid_bearer_token(): void
    {
        $this->getJson('/api/folders')
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthorized.',
            ]);

        $this->withToken('invalid-token')
            ->getJson('/api/folders')
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthorized.',
            ]);
    }

    public function test_folders_endpoint_returns_only_visible_folders_with_nested_projects(): void
    {
        $viewer = $this->makeUser();

        $visibleFolder = Folder::query()->create([
            'name' => 'Открытая папка',
            'hidden' => false,
            'visible_for' => [],
        ]);
        $hiddenVisibleFolder = Folder::query()->create([
            'name' => 'Скрытая видимая папка',
            'hidden' => true,
            'visible_for' => [$viewer->id],
        ]);
        Folder::query()->create([
            'name' => 'Скрытая чужая папка',
            'hidden' => true,
            'visible_for' => [],
        ]);

        Project::query()->create([
            'folder_id' => $visibleFolder->id,
            'name' => 'Проект A',
            'tags' => null,
        ]);
        Project::query()->create([
            'folder_id' => $visibleFolder->id,
            'name' => 'Проект B',
            'tags' => null,
        ]);
        Project::query()->create([
            'folder_id' => $hiddenVisibleFolder->id,
            'name' => 'Проект C',
            'tags' => null,
        ]);

        $expected = app(ProjectFoldersQuery::class)
            ->get($viewer)
            ->map(function (Folder $folder): array {
                return [
                    ...app(McpPresenter::class)->folder($folder, includeVisibility: false),
                    'projects' => $folder->projects
                        ->map(fn (Project $project): array => app(McpPresenter::class)->project($project))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $this->withToken((string) $viewer->access_token)
            ->getJson('/api/folders')
            ->assertOk()
            ->assertExactJson([
                'data' => $expected,
            ]);
    }

    private function makeUser(int $accessLevel = User::ACCESS_LEVEL_ADMIN): User
    {
        return User::factory()->create([
            'access_token' => (string) Str::uuid(),
            'access_level' => $accessLevel,
        ]);
    }
}
