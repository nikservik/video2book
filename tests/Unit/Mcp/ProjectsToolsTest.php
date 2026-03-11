<?php

namespace Tests\Unit\Mcp;

use App\Actions\Lesson\UpdateLessonAudioDurationAction;
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
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Project;
use App\Models\ProjectTag;
use App\Models\Step;
use App\Models\StepVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProjectsToolsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_list_folder_projects_tool_returns_projects_with_lessons_count_and_duration(): void
    {
        $this->ensureDefaultProjectTag();

        $viewer = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_ADMIN,
        ]);
        $folder = Folder::query()->create([
            'name' => 'Папка проектов',
            'hidden' => false,
            'visible_for' => [],
        ]);

        $oldProject = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Старый проект',
            'tags' => null,
            'settings' => ['lessons_audio_duration_seconds' => 600],
        ]);
        $newProject = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Новый проект',
            'tags' => null,
            'settings' => ['lessons_audio_duration_seconds' => 3600],
        ]);

        Lesson::query()->create([
            'project_id' => $oldProject->id,
            'name' => 'Урок 1',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);
        Lesson::query()->create([
            'project_id' => $newProject->id,
            'name' => 'Урок 2',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);
        Lesson::query()->create([
            'project_id' => $newProject->id,
            'name' => 'Урок 3',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        $oldProject->forceFill([
            'updated_at' => Carbon::parse('2026-02-01 10:00:00'),
        ])->saveQuietly();
        $newProject->forceFill([
            'updated_at' => Carbon::parse('2026-02-05 10:00:00'),
        ])->saveQuietly();

        Video2BookServer::actingAs($viewer)
            ->tool(ListFolderProjectsTool::class, [
                'folder_id' => $folder->id,
            ])
            ->assertOk()
            ->assertStructuredContent([
                'folder' => [
                    'id' => $folder->id,
                    'name' => 'Папка проектов',
                    'hidden' => false,
                    'projects_count' => 2,
                    'visible_for_user_ids' => [],
                ],
                'projects' => [
                    [
                        'id' => $newProject->id,
                        'folder_id' => $folder->id,
                        'name' => 'Новый проект',
                        'lessons_count' => 2,
                        'duration_seconds' => 3600,
                        'duration_label' => '1ч 0м',
                        'default_pipeline_version_id' => null,
                        'referer' => null,
                        'updated_at' => Carbon::parse('2026-02-05 10:00:00')->toISOString(),
                    ],
                    [
                        'id' => $oldProject->id,
                        'folder_id' => $folder->id,
                        'name' => 'Старый проект',
                        'lessons_count' => 1,
                        'duration_seconds' => 600,
                        'duration_label' => '10м',
                        'default_pipeline_version_id' => null,
                        'referer' => null,
                        'updated_at' => Carbon::parse('2026-02-01 10:00:00')->toISOString(),
                    ],
                ],
            ]);
    }

    public function test_create_project_tool_creates_project_in_selected_folder(): void
    {
        $viewer = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_ADMIN,
        ]);
        $folder = Folder::query()->create([
            'name' => 'Папка для MCP',
            'hidden' => false,
            'visible_for' => [],
        ]);

        Video2BookServer::actingAs($viewer)
            ->tool(CreateProjectTool::class, [
                'folder_id' => $folder->id,
                'name' => 'Новый MCP проект',
                'referer' => null,
                'default_pipeline_version_id' => null,
                'lessons_list' => null,
            ])
            ->assertOk();

        $project = Project::query()->where('name', 'Новый MCP проект')->firstOrFail();

        $this->assertSame($folder->id, $project->folder_id);
        $this->assertNull($project->referer);
        $this->assertNull($project->default_pipeline_version_id);
    }

    public function test_update_project_tool_updates_name_referer_and_default_pipeline(): void
    {
        $viewer = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_ADMIN,
        ]);
        $folder = Folder::query()->create([
            'name' => 'Папка',
            'hidden' => false,
            'visible_for' => [],
        ]);
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Старое имя',
            'tags' => null,
            'settings' => [],
        ]);
        $pipelineVersion = $this->createPipelineVersion();

        Video2BookServer::actingAs($viewer)
            ->tool(UpdateProjectTool::class, [
                'project_id' => $project->id,
                'name' => 'Новое имя',
                'referer' => 'https://example.com/ref',
                'default_pipeline_version_id' => $pipelineVersion->id,
            ])
            ->assertOk();

        $project->refresh();

        $this->assertSame('Новое имя', $project->name);
        $this->assertSame('https://example.com/ref', $project->referer);
        $this->assertSame($pipelineVersion->id, $project->default_pipeline_version_id);
    }

    public function test_recalculate_project_lessons_duration_tool_returns_total_duration(): void
    {
        $this->ensureDefaultProjectTag();

        $viewer = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_ADMIN,
        ]);
        $folder = Folder::query()->create([
            'name' => 'Папка длительности',
            'hidden' => false,
            'visible_for' => [],
        ]);
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Проект',
            'tags' => null,
            'settings' => [],
        ]);

        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок 1',
            'tag' => 'default',
            'source_filename' => 'lessons/1.mp3',
            'settings' => [
                UpdateLessonAudioDurationAction::LESSON_DURATION_SETTING_KEY => 1200,
            ],
        ]);
        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок 2',
            'tag' => 'default',
            'source_filename' => 'lessons/2.mp3',
            'settings' => [
                UpdateLessonAudioDurationAction::LESSON_DURATION_SETTING_KEY => 1800,
            ],
        ]);

        Video2BookServer::actingAs($viewer)
            ->tool(RecalculateProjectLessonsDurationTool::class, [
                'project_id' => $project->id,
            ])
            ->assertOk()
            ->assertStructuredContent([
                'project_id' => $project->id,
                'total_duration_seconds' => 3000,
                'total_duration_label' => '50м',
            ]);

        $this->assertSame(3000, data_get($project->fresh()->settings, 'lessons_audio_duration_seconds'));
    }

    public function test_list_project_export_options_tool_returns_done_versions_with_text_steps(): void
    {
        $this->ensureDefaultProjectTag();

        $viewer = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_ADMIN,
        ]);
        $folder = Folder::query()->create([
            'name' => 'Папка экспорта',
            'hidden' => false,
            'visible_for' => [],
        ]);
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Проект экспорта',
            'tags' => null,
            'settings' => [],
        ]);
        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        [, $doneVersion, $doneTextStep] = $this->createPipelineVersionWithSteps([
            ['name' => 'Транскрибация', 'type' => 'transcribe'],
            ['name' => 'Текстовый шаг', 'type' => 'text'],
            ['name' => 'Глоссарий', 'type' => 'glossary'],
        ]);
        [, $queuedVersion] = $this->createPipelineVersionWithSteps([
            ['name' => 'Текстовый шаг queued', 'type' => 'text'],
        ]);

        PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $doneVersion->id,
            'status' => 'done',
            'state' => [],
        ]);
        PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $queuedVersion->id,
            'status' => 'queued',
            'state' => [],
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
                    'name' => 'Проект экспорта',
                    'lessons_count' => 1,
                    'duration_seconds' => null,
                    'duration_label' => null,
                    'default_pipeline_version_id' => null,
                    'referer' => null,
                    'updated_at' => $project->fresh()->updated_at?->toISOString(),
                ],
                'download_modes' => $this->expectedExportDownloadModes(),
                'pipeline_versions' => [
                    [
                        'id' => $doneVersion->id,
                        'label' => 'Пайплайн • v1',
                        'steps' => [
                            [
                                'id' => $doneTextStep->id,
                                'name' => 'Текстовый шаг',
                            ],
                        ],
                    ],
                ],
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

    private function createPipelineVersion(): PipelineVersion
    {
        $pipeline = Pipeline::query()->create();
        $version = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Pipeline title',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);

        $pipeline->update([
            'current_version_id' => $version->id,
        ]);

        return $version;
    }

    /**
     * @param  array<int, array{name:string,type:string}>  $steps
     * @return array{0: Pipeline, 1: PipelineVersion, 2: StepVersion}
     */
    private function createPipelineVersionWithSteps(array $steps): array
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

        $textStepVersion = null;

        foreach ($steps as $index => $stepData) {
            $step = Step::query()->create([
                'pipeline_id' => $pipeline->id,
                'current_version_id' => null,
            ]);

            $stepVersion = StepVersion::query()->create([
                'step_id' => $step->id,
                'input_step_id' => null,
                'name' => $stepData['name'],
                'type' => $stepData['type'],
                'version' => 1,
                'description' => null,
                'prompt' => 'Prompt',
                'settings' => [
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                ],
                'status' => 'active',
            ]);

            $step->update(['current_version_id' => $stepVersion->id]);

            PipelineVersionStep::query()->create([
                'pipeline_version_id' => $version->id,
                'step_version_id' => $stepVersion->id,
                'position' => $index + 1,
            ]);

            if ($stepData['type'] === 'text') {
                $textStepVersion = $stepVersion;
            }
        }

        return [$pipeline, $version, $textStepVersion];
    }
}
