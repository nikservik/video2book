<?php

namespace Tests\Unit\Mcp;

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
use App\Models\User;
use App\Support\LessonTagResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Testing\TestResponse;
use ReflectionProperty;
use Tests\TestCase;

class ProjectToolsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_list_folder_projects_tool_returns_projects_from_folder(): void
    {
        $viewer = User::factory()->create([
            'access_token' => (string) Str::uuid(),
        ]);
        $folder = Folder::query()->create([
            'name' => 'Projects',
            'hidden' => false,
            'visible_for' => [],
        ]);

        $projectOne = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Project one',
            'settings' => [
                'lessons_audio_duration_seconds' => 180,
            ],
        ]);
        $projectTwo = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Project two',
            'settings' => [
                'lessons_audio_duration_seconds' => 3600,
            ],
        ]);

        Lesson::query()->create([
            'project_id' => $projectOne->id,
            'name' => 'Lesson 1',
            'tag' => LessonTagResolver::resolve(null),
            'settings' => [],
        ]);
        Lesson::query()->create([
            'project_id' => $projectTwo->id,
            'name' => 'Lesson 2',
            'tag' => LessonTagResolver::resolve(null),
            'settings' => [],
        ]);
        Lesson::query()->create([
            'project_id' => $projectTwo->id,
            'name' => 'Lesson 3',
            'tag' => LessonTagResolver::resolve(null),
            'settings' => [],
        ]);

        $content = $this->structuredContent(
            Video2BookServer::actingAs($viewer)->tool(ListFolderProjectsTool::class, [
                'folder_id' => $folder->id,
            ])
        );

        $this->assertSame($folder->id, $content['folder']['id']);
        $this->assertCount(2, $content['projects']);
        $projectsByName = collect($content['projects'])->keyBy('name');

        $this->assertSame(2, $projectsByName['Project two']['lessons_count']);
        $this->assertSame(3600, $projectsByName['Project two']['duration_seconds']);
        $this->assertSame('1ч 0м', $projectsByName['Project two']['duration_label']);
        $this->assertSame(1, $projectsByName['Project one']['lessons_count']);
        $this->assertSame(180, $projectsByName['Project one']['duration_seconds']);
        $this->assertSame('3м', $projectsByName['Project one']['duration_label']);
    }

    public function test_create_project_tool_creates_project_with_pipeline_version_and_referer(): void
    {
        $viewer = User::factory()->create([
            'access_token' => (string) Str::uuid(),
        ]);
        $folder = Folder::query()->create([
            'name' => 'Folder',
            'hidden' => false,
            'visible_for' => [],
        ]);
        $pipelineVersion = $this->createActivePipelineVersion('Default template');

        $content = $this->structuredContent(
            Video2BookServer::actingAs($viewer)->tool(CreateProjectTool::class, [
                'folder_id' => $folder->id,
                'name' => 'MCP project',
                'referer' => 'https://example.com/video',
                'default_pipeline_version_id' => $pipelineVersion->id,
            ])
        );

        $project = Project::query()->firstWhere('name', 'MCP project');

        $this->assertNotNull($project);
        $this->assertSame($folder->id, (int) $project->folder_id);
        $this->assertSame($pipelineVersion->id, (int) $project->default_pipeline_version_id);
        $this->assertSame('https://example.com/video', $project->referer);
        $this->assertSame($project->id, $content['project']['id']);
        $this->assertSame('MCP project', $content['project']['name']);
        $this->assertSame($pipelineVersion->id, $content['project']['default_pipeline_version_id']);
    }

    public function test_update_project_tool_updates_project_fields(): void
    {
        $viewer = User::factory()->create([
            'access_token' => (string) Str::uuid(),
        ]);
        $folder = Folder::query()->create([
            'name' => 'Folder',
            'hidden' => false,
            'visible_for' => [],
        ]);
        $oldPipelineVersion = $this->createActivePipelineVersion('Old template');
        $newPipelineVersion = $this->createActivePipelineVersion('New template');

        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Old name',
            'default_pipeline_version_id' => $oldPipelineVersion->id,
            'referer' => 'https://old.example.com',
        ]);

        $content = $this->structuredContent(
            Video2BookServer::actingAs($viewer)->tool(UpdateProjectTool::class, [
                'project_id' => $project->id,
                'name' => 'New name',
                'referer' => 'https://new.example.com',
                'default_pipeline_version_id' => $newPipelineVersion->id,
            ])
        );

        $project->refresh();

        $this->assertSame('New name', $project->name);
        $this->assertSame('https://new.example.com', $project->referer);
        $this->assertSame($newPipelineVersion->id, (int) $project->default_pipeline_version_id);
        $this->assertSame('New name', $content['project']['name']);
        $this->assertSame('https://new.example.com', $content['project']['referer']);
    }

    public function test_recalculate_project_lessons_duration_tool_returns_updated_total(): void
    {
        $viewer = User::factory()->create([
            'access_token' => (string) Str::uuid(),
        ]);
        $folder = Folder::query()->create([
            'name' => 'Folder',
            'hidden' => false,
            'visible_for' => [],
        ]);
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Duration project',
            'settings' => [],
        ]);

        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson 1',
            'tag' => LessonTagResolver::resolve(null),
            'source_filename' => 'lessons/1.mp3',
            'settings' => ['audio_duration_seconds' => 120],
        ]);
        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson 2',
            'tag' => LessonTagResolver::resolve(null),
            'source_filename' => 'lessons/2.mp3',
            'settings' => ['audio_duration_seconds' => 61],
        ]);
        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson 3',
            'tag' => LessonTagResolver::resolve(null),
            'source_filename' => null,
            'settings' => ['audio_duration_seconds' => 999],
        ]);

        $content = $this->structuredContent(
            Video2BookServer::actingAs($viewer)->tool(RecalculateProjectLessonsDurationTool::class, [
                'project_id' => $project->id,
            ])
        );

        $project->refresh();

        $this->assertSame(181, $content['total_duration_seconds']);
        $this->assertSame('3м', $content['total_duration_label']);
        $this->assertSame(181, data_get($project->settings, 'lessons_audio_duration_seconds'));
    }

    public function test_list_project_export_options_tool_returns_done_text_steps(): void
    {
        $viewer = User::factory()->create([
            'access_token' => (string) Str::uuid(),
        ]);
        $folder = Folder::query()->create([
            'name' => 'Folder',
            'hidden' => false,
            'visible_for' => [],
        ]);
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Export project',
            'settings' => [],
        ]);
        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson',
            'tag' => LessonTagResolver::resolve(null),
            'settings' => [],
        ]);

        $pipeline = Pipeline::query()->create();
        $pipelineVersion = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Template',
            'description' => 'Description',
            'status' => 'active',
        ]);
        $pipeline->update(['current_version_id' => $pipelineVersion->id]);

        $textStep = $pipeline->steps()->create();
        $textStepVersion = $textStep->versions()->create([
            'name' => 'Summary',
            'type' => 'text',
            'version' => 1,
            'settings' => [],
            'status' => 'active',
        ]);
        $textStep->update(['current_version_id' => $textStepVersion->id]);

        $transcribeStep = $pipeline->steps()->create();
        $transcribeStepVersion = $transcribeStep->versions()->create([
            'name' => 'Transcript',
            'type' => 'transcribe',
            'version' => 1,
            'settings' => [],
            'status' => 'active',
        ]);
        $transcribeStep->update(['current_version_id' => $transcribeStepVersion->id]);

        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $pipelineVersion->id,
            'step_version_id' => $textStepVersion->id,
            'position' => 1,
        ]);
        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $pipelineVersion->id,
            'step_version_id' => $transcribeStepVersion->id,
            'position' => 2,
        ]);

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
            'result' => '# Summary',
        ]);
        PipelineRunStep::query()->create([
            'pipeline_run_id' => $run->id,
            'step_version_id' => $transcribeStepVersion->id,
            'position' => 2,
            'status' => 'done',
            'result' => 'Raw transcript',
        ]);

        $content = $this->structuredContent(
            Video2BookServer::actingAs($viewer)->tool(ListProjectExportOptionsTool::class, [
                'project_id' => $project->id,
            ])
        );

        $this->assertSame($project->id, $content['project']['id']);
        $this->assertSame($this->expectedExportDownloadModes(), $content['download_modes']);
        $this->assertCount(1, $content['pipeline_versions']);
        $this->assertSame($pipelineVersion->id, $content['pipeline_versions'][0]['id']);
        $this->assertSame('Template • v1', $content['pipeline_versions'][0]['label']);
        $this->assertSame([
            [
                'id' => $textStepVersion->id,
                'name' => 'Summary',
            ],
        ], $content['pipeline_versions'][0]['steps']);
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

    private function createActivePipelineVersion(string $title): PipelineVersion
    {
        $pipeline = Pipeline::query()->create();
        $pipelineVersion = $pipeline->versions()->create([
            'version' => 1,
            'title' => $title,
            'description' => 'Description',
            'status' => 'active',
        ]);

        $pipeline->update([
            'current_version_id' => $pipelineVersion->id,
        ]);

        return $pipelineVersion;
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
}
