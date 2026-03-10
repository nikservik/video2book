<?php

namespace Tests\Feature\Mcp;

use App\Actions\Project\RecalculateProjectLessonsAudioDurationAction;
use App\Jobs\DownloadLessonAudioJob;
use App\Mcp\Servers\Video2BookServer;
use App\Mcp\Support\McpPresenter;
use App\Mcp\Tools\Folders\CreateProjectFolderTool;
use App\Mcp\Tools\Folders\ListProjectFoldersTool;
use App\Mcp\Tools\Projects\CreateProjectTool;
use App\Mcp\Tools\Projects\ListFolderProjectsTool;
use App\Mcp\Tools\Projects\ListProjectExportOptionsTool;
use App\Mcp\Tools\Projects\RecalculateProjectLessonsDurationTool;
use App\Mcp\Tools\Projects\UpdateProjectTool;
use App\Models\Folder;
use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Project;
use App\Models\ProjectTag;
use App\Models\Step;
use App\Models\StepVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FolderProjectToolsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_list_project_folders_tool_returns_only_visible_folders_with_projects_count(): void
    {
        $viewer = $this->createUser(User::ACCESS_LEVEL_ADMIN, 'viewer@example.com');
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
            'name' => 'Проект 1',
            'tags' => null,
        ]);
        Project::query()->create([
            'folder_id' => $visibleFolder->id,
            'name' => 'Проект 2',
            'tags' => null,
        ]);
        Project::query()->create([
            'folder_id' => $hiddenVisibleFolder->id,
            'name' => 'Проект 3',
            'tags' => null,
        ]);

        $response = Video2BookServer::actingAs($viewer)
            ->tool(ListProjectFoldersTool::class);

        $response
            ->assertOk()
            ->assertAuthenticatedAs($viewer)
            ->assertStructuredContent([
                'folders' => app(\App\Services\Project\ProjectFoldersQuery::class)->get($viewer)
                    ->map(fn ($folder): array => app(McpPresenter::class)->folder($folder))
                    ->values()
                    ->all(),
            ]);
    }

    public function test_create_project_folder_tool_adds_locked_users_for_hidden_folder(): void
    {
        $viewer = $this->createUser(User::ACCESS_LEVEL_ADMIN, 'viewer@example.com');
        $superAdmin = $this->createUser(User::ACCESS_LEVEL_SUPERADMIN, 'superadmin@example.com');
        $visibleUser = $this->createUser(User::ACCESS_LEVEL_USER, 'visible@example.com');

        $response = Video2BookServer::actingAs($viewer)->tool(CreateProjectFolderTool::class, [
            'name' => 'Новая скрытая папка',
            'hidden' => true,
            'visible_for_user_ids' => [$visibleUser->id],
        ]);

        $folder = Folder::query()->where('name', 'Новая скрытая папка')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            User::query()
                ->where('access_level', User::ACCESS_LEVEL_SUPERADMIN)
                ->pluck('id')
                ->push($viewer->id)
                ->push($visibleUser->id)
                ->map(static fn (mixed $userId): int => (int) $userId)
                ->unique()
                ->values()
                ->all(),
            array_map(static fn (mixed $userId): int => (int) $userId, (array) $folder->visible_for)
        );

        $response
            ->assertOk()
            ->assertStructuredContent([
                'folder' => app(McpPresenter::class)->folder($folder),
            ]);
    }

    public function test_list_folder_projects_tool_returns_projects_for_visible_folder(): void
    {
        $this->ensureDefaultProjectTag();

        $viewer = $this->createUser(User::ACCESS_LEVEL_ADMIN, 'viewer@example.com');
        $folder = Folder::query()->create([
            'name' => 'Учебные проекты',
            'hidden' => false,
            'visible_for' => [],
        ]);

        $firstProject = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Проект A',
            'tags' => null,
            'settings' => [
                RecalculateProjectLessonsAudioDurationAction::PROJECT_TOTAL_DURATION_SETTING_KEY => 3600,
            ],
        ]);
        $secondProject = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Проект B',
            'tags' => null,
            'settings' => [
                RecalculateProjectLessonsAudioDurationAction::PROJECT_TOTAL_DURATION_SETTING_KEY => 1800,
            ],
        ]);

        Lesson::query()->create([
            'project_id' => $firstProject->id,
            'name' => 'Урок 1',
            'tag' => 'default',
            'source_filename' => 'lessons/1.mp3',
            'settings' => ['audio_duration_seconds' => 3600],
        ]);

        $response = Video2BookServer::actingAs($viewer)->tool(ListFolderProjectsTool::class, [
            'folder_id' => $folder->id,
        ]);
        $expectedFolder = app(\App\Services\Project\ProjectFoldersQuery::class)
            ->get($viewer)
            ->firstWhere('id', $folder->id);

        $response
            ->assertOk()
            ->assertStructuredContent([
                'folder' => app(McpPresenter::class)->folder($expectedFolder),
                'projects' => $expectedFolder->projects
                    ->map(fn ($project): array => app(McpPresenter::class)->project($project))
                    ->values()
                    ->all(),
            ]);
    }

    public function test_list_folder_projects_tool_rejects_invisible_folder(): void
    {
        $viewer = $this->createUser(User::ACCESS_LEVEL_USER, 'viewer@example.com');
        $folder = Folder::query()->create([
            'name' => 'Скрытая папка',
            'hidden' => true,
            'visible_for' => [],
        ]);

        Video2BookServer::actingAs($viewer)
            ->tool(ListFolderProjectsTool::class, [
                'folder_id' => $folder->id,
            ])
            ->assertHasErrors(['Папка не найдена или недоступна.']);
    }

    public function test_create_project_tool_creates_project_and_queues_lessons_from_list(): void
    {
        Queue::fake();

        $viewer = $this->createUser(User::ACCESS_LEVEL_ADMIN, 'viewer@example.com');
        $folder = Folder::query()->create([
            'name' => 'Новая папка',
            'hidden' => false,
            'visible_for' => [],
        ]);
        $pipelineVersion = $this->createPipelineVersionWithStep('transcribe', 'Транскрибация');

        $response = Video2BookServer::actingAs($viewer)->tool(CreateProjectTool::class, [
            'folder_id' => $folder->id,
            'name' => 'MCP проект',
            'referer' => 'https://example.com/watch',
            'default_pipeline_version_id' => $pipelineVersion->id,
            'lessons_list' => "Урок 1\nhttps://example.com/1\nУрок 2\nhttps://example.com/2",
        ]);

        $project = Project::query()->where('name', 'MCP проект')->firstOrFail();

        $this->assertSame($pipelineVersion->id, $project->default_pipeline_version_id);
        $this->assertSame('https://example.com/watch', $project->referer);
        $this->assertSame(2, $project->lessons()->count());
        Queue::assertPushed(DownloadLessonAudioJob::class, 2);

        $response
            ->assertOk()
            ->assertStructuredContent([
                'project' => app(McpPresenter::class)->project($project->fresh()->loadCount('lessons')),
            ]);
    }

    public function test_update_project_tool_updates_project_fields(): void
    {
        $viewer = $this->createUser(User::ACCESS_LEVEL_ADMIN, 'viewer@example.com');
        $project = Project::query()->create([
            'name' => 'Исходный проект',
            'tags' => null,
            'referer' => null,
            'default_pipeline_version_id' => null,
        ]);
        $pipelineVersion = $this->createPipelineVersionWithStep('text', 'Конспект');

        $response = Video2BookServer::actingAs($viewer)->tool(UpdateProjectTool::class, [
            'project_id' => $project->id,
            'name' => 'Обновлённый проект',
            'referer' => 'https://example.com/ref',
            'default_pipeline_version_id' => $pipelineVersion->id,
        ]);

        $project = $project->fresh()->loadCount('lessons');

        $this->assertSame('Обновлённый проект', $project->name);
        $this->assertSame('https://example.com/ref', $project->referer);
        $this->assertSame($pipelineVersion->id, $project->default_pipeline_version_id);

        $response
            ->assertOk()
            ->assertStructuredContent([
                'project' => app(McpPresenter::class)->project($project),
            ]);
    }

    public function test_recalculate_project_lessons_duration_tool_returns_updated_total(): void
    {
        $this->ensureDefaultProjectTag();

        $viewer = $this->createUser(User::ACCESS_LEVEL_ADMIN, 'viewer@example.com');
        $project = Project::query()->create([
            'name' => 'Проект с уроками',
            'tags' => null,
        ]);

        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок 1',
            'tag' => 'default',
            'source_filename' => 'lessons/1.mp3',
            'settings' => ['audio_duration_seconds' => 3600],
        ]);
        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок 2',
            'tag' => 'default',
            'source_filename' => 'lessons/2.mp3',
            'settings' => ['audio_duration_seconds' => 1800],
        ]);

        Video2BookServer::actingAs($viewer)
            ->tool(RecalculateProjectLessonsDurationTool::class, [
                'project_id' => $project->id,
            ])
            ->assertOk()
            ->assertStructuredContent([
                'project_id' => $project->id,
                'total_duration_seconds' => 5400,
                'total_duration_label' => '1ч 30м',
            ]);
    }

    public function test_list_project_export_options_tool_returns_pipeline_versions_and_text_steps(): void
    {
        $this->ensureDefaultProjectTag();

        $viewer = $this->createUser(User::ACCESS_LEVEL_ADMIN, 'viewer@example.com');
        $project = Project::query()->create([
            'name' => 'Проект для экспорта',
            'tags' => null,
        ]);
        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок',
            'tag' => 'default',
            'source_filename' => 'lessons/1.mp3',
            'settings' => ['audio_duration_seconds' => 1200],
        ]);
        $pipelineVersion = $this->createPipelineVersionWithStep('text', 'Конспект');
        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'done',
            'state' => [],
        ]);

        $run->steps()->create([
            'step_version_id' => $this->latestStepVersionId($pipelineVersion),
            'position' => 1,
            'status' => 'done',
            'result' => 'Готовый текст',
            'error' => null,
            'input_tokens' => 10,
            'output_tokens' => 20,
            'cost' => 0.1234,
        ]);

        $response = Video2BookServer::actingAs($viewer)->tool(ListProjectExportOptionsTool::class, [
            'project_id' => $project->id,
        ]);

        $response
            ->assertOk()
            ->assertStructuredContent([
                'project' => app(McpPresenter::class)->project($project->fresh()->loadCount('lessons')),
                'pipeline_versions' => [
                    [
                        'id' => $pipelineVersion->id,
                        'label' => 'Пайплайн • v1',
                        'steps' => [
                            [
                                'id' => $this->latestStepVersionId($pipelineVersion),
                                'name' => 'Конспект',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    private function createUser(int $accessLevel, string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'access_level' => $accessLevel,
        ]);
    }

    private function ensureDefaultProjectTag(): void
    {
        ProjectTag::query()->firstOrCreate([
            'slug' => 'default',
        ], [
            'description' => null,
        ]);
    }

    private function createPipelineVersionWithStep(string $stepType, string $stepName): PipelineVersion
    {
        $pipeline = Pipeline::query()->create();
        $version = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Пайплайн',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);
        $pipeline->update(['current_version_id' => $version->id]);

        $step = Step::query()->create([
            'pipeline_id' => $pipeline->id,
            'current_version_id' => null,
        ]);
        $stepVersion = StepVersion::query()->create([
            'step_id' => $step->id,
            'input_step_id' => null,
            'name' => $stepName,
            'type' => $stepType,
            'version' => 1,
            'description' => null,
            'prompt' => $stepType === 'transcribe' ? 'Transcribe audio' : 'Generate text',
            'settings' => $stepType === 'transcribe'
                ? [
                    'provider' => 'openai',
                    'model' => 'whisper-1',
                    'temperature' => 0,
                ]
                : [
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                    'temperature' => 0,
                ],
            'status' => 'active',
        ]);
        $step->update(['current_version_id' => $stepVersion->id]);

        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $version->id,
            'step_version_id' => $stepVersion->id,
            'position' => 1,
        ]);

        return $version;
    }

    private function latestStepVersionId(PipelineVersion $pipelineVersion): int
    {
        return (int) $pipelineVersion->versionSteps()->value('step_version_id');
    }
}
