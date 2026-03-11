<?php

namespace Tests\Unit\Mcp\Projects;

use App\Actions\Project\RecalculateProjectLessonsAudioDurationAction;
use App\Mcp\Servers\Video2BookServer;
use App\Mcp\Tools\Projects\CreateProjectTool;
use App\Mcp\Tools\Projects\ListFolderProjectsTool;
use App\Mcp\Tools\Projects\ListProjectExportOptionsTool;
use App\Mcp\Tools\Projects\RecalculateProjectLessonsDurationTool;
use App\Mcp\Tools\Projects\UpdateProjectTool;
use App\Models\Folder;
use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Project;
use App\Models\ProjectTag;
use App\Models\Step;
use App\Models\StepVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectsToolsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_list_folder_projects_tool_returns_projects_with_duration_and_lessons_count(): void
    {
        $viewer = $this->makeUser();
        $folder = Folder::query()->create([
            'name' => 'Folder',
            'hidden' => false,
            'visible_for' => [],
        ]);
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Project One',
            'tags' => null,
            'settings' => [
                RecalculateProjectLessonsAudioDurationAction::PROJECT_TOTAL_DURATION_SETTING_KEY => 3600,
            ],
        ]);

        ProjectTag::query()->create(['slug' => 'default', 'description' => null]);

        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson One',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        Video2BookServer::actingAs($viewer)
            ->tool(ListFolderProjectsTool::class, [
                'folder_id' => $folder->id,
            ])
            ->assertOk()
            ->assertStructuredContent([
                'folder' => [
                    'id' => $folder->id,
                    'name' => 'Folder',
                    'hidden' => false,
                    'projects_count' => 1,
                    'visible_for_user_ids' => [],
                ],
                'projects' => [
                    [
                        'id' => $project->id,
                        'folder_id' => $folder->id,
                        'name' => 'Project One',
                        'lessons_count' => 1,
                        'duration_seconds' => 3600,
                        'duration_label' => '1ч 0м',
                        'default_pipeline_version_id' => null,
                        'referer' => null,
                        'updated_at' => $project->updated_at?->toISOString(),
                    ],
                ],
            ]);
    }

    public function test_create_project_tool_creates_project_and_youtube_lessons(): void
    {
        Queue::fake();

        $viewer = $this->makeUser();
        $folder = Folder::query()->create([
            'name' => 'Folder',
            'hidden' => false,
            'visible_for' => [],
        ]);
        [, $pipelineVersion] = $this->createPipelineVersionWithTextStep();

        Video2BookServer::actingAs($viewer)
            ->tool(CreateProjectTool::class, [
                'folder_id' => $folder->id,
                'name' => 'Created Project',
                'referer' => 'https://example.com',
                'default_pipeline_version_id' => $pipelineVersion->id,
                'lessons_list' => "Lesson A\nhttps://youtube.com/watch?v=1",
            ])
            ->assertOk();

        $project = Project::query()->withCount('lessons')->firstOrFail();

        $this->assertSame('Created Project', $project->name);
        $this->assertSame('https://example.com', $project->referer);
        $this->assertSame($pipelineVersion->id, $project->default_pipeline_version_id);
        $this->assertSame(1, $project->lessons_count);
    }

    public function test_update_project_tool_updates_project_fields(): void
    {
        $viewer = $this->makeUser();
        $folder = Folder::query()->create([
            'name' => 'Folder',
            'hidden' => false,
            'visible_for' => [],
        ]);
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Old Name',
            'tags' => null,
        ]);
        [, $pipelineVersion] = $this->createPipelineVersionWithTextStep();

        Video2BookServer::actingAs($viewer)
            ->tool(UpdateProjectTool::class, [
                'project_id' => $project->id,
                'name' => 'New Name',
                'referer' => 'https://example.com/ref',
                'default_pipeline_version_id' => $pipelineVersion->id,
            ])
            ->assertOk();

        $project->refresh();

        $this->assertSame('New Name', $project->name);
        $this->assertSame('https://example.com/ref', $project->referer);
        $this->assertSame($pipelineVersion->id, $project->default_pipeline_version_id);
    }

    public function test_recalculate_project_lessons_duration_tool_returns_total_duration(): void
    {
        $viewer = $this->makeUser();
        $folder = Folder::query()->create([
            'name' => 'Folder',
            'hidden' => false,
            'visible_for' => [],
        ]);
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Project',
            'tags' => null,
            'settings' => [],
        ]);

        ProjectTag::query()->create(['slug' => 'default', 'description' => null]);

        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson One',
            'tag' => 'default',
            'source_filename' => 'lessons/1.mp3',
            'settings' => ['audio_duration_seconds' => 120],
        ]);
        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson Two',
            'tag' => 'default',
            'source_filename' => 'lessons/2.mp3',
            'settings' => ['audio_duration_seconds' => 60],
        ]);

        Video2BookServer::actingAs($viewer)
            ->tool(RecalculateProjectLessonsDurationTool::class, [
                'project_id' => $project->id,
            ])
            ->assertOk()
            ->assertStructuredContent([
                'project_id' => $project->id,
                'total_duration_seconds' => 180,
                'total_duration_label' => '3м',
            ]);
    }

    public function test_list_project_export_options_tool_returns_done_text_steps(): void
    {
        $viewer = $this->makeUser();
        $folder = Folder::query()->create([
            'name' => 'Folder',
            'hidden' => false,
            'visible_for' => [],
        ]);
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Project',
            'tags' => null,
        ]);

        ProjectTag::query()->create(['slug' => 'default', 'description' => null]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        [, $pipelineVersion, $textStepVersion] = $this->createPipelineVersionWithTextStep();

        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'done',
            'state' => [],
        ]);

        PipelineRunStep::query()->create([
            'pipeline_run_id' => $run->id,
            'step_version_id' => $textStepVersion->id,
            'position' => 1,
            'status' => 'done',
            'result' => '# Result',
        ]);

        Video2BookServer::actingAs($viewer)
            ->tool(ListProjectExportOptionsTool::class, [
                'project_id' => $project->id,
            ])
            ->assertOk()
            ->assertStructuredContent([
                'project' => [
                    'id' => $project->id,
                    'folder_id' => $folder->id,
                    'name' => 'Project',
                    'lessons_count' => 1,
                    'duration_seconds' => null,
                    'duration_label' => null,
                    'default_pipeline_version_id' => null,
                    'referer' => null,
                    'updated_at' => $project->updated_at?->toISOString(),
                ],
                'download_modes' => $this->expectedExportDownloadModes(),
                'pipeline_versions' => [
                    [
                        'id' => $pipelineVersion->id,
                        'label' => 'Pipeline • v1',
                        'steps' => [
                            [
                                'id' => $textStepVersion->id,
                                'name' => 'Summary',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    private function makeUser(int $accessLevel = User::ACCESS_LEVEL_ADMIN): User
    {
        return User::factory()->create([
            'access_token' => (string) Str::uuid(),
            'access_level' => $accessLevel,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expectedExportDownloadModes(): array
    {
        return [
            [
                'id' => 'single_file',
                'label' => 'Одним файлом',
                'default' => true,
                'description' => 'Объединяет результаты всех подходящих уроков проекта в один файл. Перед каждым уроком добавляется заголовок первого уровня, внутренние заголовки шага сдвигаются на один уровень глубже.',
                'formats' => [
                    ['id' => 'md', 'resource_uri_template' => 'video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/single-file/markdown'],
                    ['id' => 'pdf', 'resource_uri_template' => 'video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/single-file/pdf'],
                    ['id' => 'docx', 'resource_uri_template' => 'video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/single-file/docx'],
                ],
            ],
            [
                'id' => 'lesson',
                'label' => 'Урок',
                'default' => false,
                'description' => 'Возвращает ZIP-архив, где для каждого урока создаётся отдельный файл.',
                'formats' => [
                    ['id' => 'md', 'resource_uri_template' => 'video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/md/lesson'],
                    ['id' => 'pdf', 'resource_uri_template' => 'video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/pdf/lesson'],
                    ['id' => 'docx', 'resource_uri_template' => 'video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/docx/lesson'],
                ],
            ],
            [
                'id' => 'lesson_step',
                'label' => 'Урок - шаг',
                'default' => false,
                'description' => 'Возвращает ZIP-архив, где для каждого урока создаётся отдельный файл с названием урока и шага.',
                'formats' => [
                    ['id' => 'md', 'resource_uri_template' => 'video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/md/lesson_step'],
                    ['id' => 'pdf', 'resource_uri_template' => 'video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/pdf/lesson_step'],
                    ['id' => 'docx', 'resource_uri_template' => 'video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/docx/lesson_step'],
                ],
            ],
        ];
    }

    /**
     * @return array{0: Pipeline, 1: PipelineVersion, 2: StepVersion}
     */
    private function createPipelineVersionWithTextStep(): array
    {
        $pipeline = Pipeline::query()->create();
        $pipelineVersion = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Pipeline',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);
        $pipeline->update(['current_version_id' => $pipelineVersion->id]);

        $step = Step::query()->create([
            'pipeline_id' => $pipeline->id,
            'current_version_id' => null,
        ]);
        $textStepVersion = StepVersion::query()->create([
            'step_id' => $step->id,
            'input_step_id' => null,
            'name' => 'Summary',
            'type' => 'text',
            'version' => 1,
            'description' => null,
            'prompt' => 'Prompt',
            'settings' => [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
            ],
            'status' => 'active',
        ]);
        $step->update(['current_version_id' => $textStepVersion->id]);

        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $pipelineVersion->id,
            'step_version_id' => $textStepVersion->id,
            'position' => 1,
        ]);

        return [$pipeline, $pipelineVersion, $textStepVersion];
    }
}
