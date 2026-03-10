<?php

namespace Tests\Unit\Mcp;

use App\Jobs\DownloadLessonAudioJob;
use App\Jobs\NormalizeUploadedLessonAudioJob;
use App\Jobs\ProcessPipelineJob;
use App\Mcp\Servers\Video2BookServer;
use App\Mcp\Tools\Lessons\AddProjectLessonsFromListTool;
use App\Mcp\Tools\Lessons\CreateProjectLessonFromAudioTool;
use App\Mcp\Tools\Lessons\CreateProjectLessonFromUrlTool;
use App\Mcp\Tools\Lessons\ListProjectLessonsTool;
use App\Mcp\Tools\Queue\ListQueueTasksTool;
use App\Mcp\Tools\Runs\GetRunStepResultTool;
use App\Mcp\Tools\Runs\ListLessonRunsTool;
use App\Mcp\Tools\Runs\ListPipelineTemplatesTool;
use App\Mcp\Tools\Runs\ListRunStepsTool;
use App\Mcp\Tools\Runs\RestartRunStepTool;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LessonsRunsQueueToolsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_list_project_lessons_tool_returns_lessons_and_their_runs(): void
    {
        [$viewer, $folder, $project] = $this->createViewerFolderAndProject('Проект уроков');
        $pipelineVersion = $this->createPipelineVersion();

        $loadedLesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок загружен',
            'tag' => 'default',
            'source_filename' => 'lessons/1.mp3',
            'settings' => [
                'audio_duration_seconds' => 600,
            ],
        ]);
        $queuedLesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок в очереди',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [
                'download_status' => 'running',
                'audio_duration_seconds' => 300,
            ],
        ]);

        PipelineRun::query()->create([
            'lesson_id' => $loadedLesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'done',
            'state' => [],
        ]);
        PipelineRun::query()->create([
            'lesson_id' => $queuedLesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'queued',
            'state' => [],
        ]);

        Video2BookServer::actingAs($viewer)
            ->tool(ListProjectLessonsTool::class, [
                'project_id' => $project->id,
            ])
            ->assertOk()
            ->assertSee([
                'Проект уроков',
                'Урок загружен',
                'Урок в очереди',
                'loaded',
                'running',
            ]);
    }

    public function test_create_project_lesson_from_url_tool_creates_lesson_and_queues_download(): void
    {
        Queue::fake();

        [$viewer, , $project] = $this->createViewerFolderAndProject();
        $pipelineVersion = $this->createPipelineVersion();

        Video2BookServer::actingAs($viewer)
            ->tool(CreateProjectLessonFromUrlTool::class, [
                'project_id' => $project->id,
                'lesson_name' => 'YouTube урок',
                'youtube_url' => 'https://www.youtube.com/watch?v=abc123',
                'pipeline_version_id' => $pipelineVersion->id,
            ])
            ->assertOk()
            ->assertSee('YouTube урок');

        $lesson = Lesson::query()->where('project_id', $project->id)->where('name', 'YouTube урок')->firstOrFail();

        $this->assertSame('https://www.youtube.com/watch?v=abc123', data_get($lesson->settings, 'url'));
        Queue::assertPushedOn(DownloadLessonAudioJob::QUEUE, DownloadLessonAudioJob::class);
    }

    public function test_create_project_lesson_from_audio_tool_creates_lesson_and_queues_normalization(): void
    {
        Queue::fake();

        [$viewer, , $project] = $this->createViewerFolderAndProject();
        $pipelineVersion = $this->createPipelineVersion();

        Video2BookServer::actingAs($viewer)
            ->tool(CreateProjectLessonFromAudioTool::class, [
                'project_id' => $project->id,
                'lesson_name' => 'Аудио урок',
                'pipeline_version_id' => $pipelineVersion->id,
                'filename' => 'lesson.wav',
                'mime_type' => 'audio/wav',
                'content_base64' => base64_encode('fake-audio-content'),
            ])
            ->assertOk()
            ->assertSee('Аудио урок');

        $lesson = Lesson::query()->where('project_id', $project->id)->where('name', 'Аудио урок')->firstOrFail();

        Queue::assertPushedOn(NormalizeUploadedLessonAudioJob::QUEUE, NormalizeUploadedLessonAudioJob::class);
        $this->assertTrue((bool) data_get($lesson->settings, 'downloading'));
    }

    public function test_add_project_lessons_from_list_tool_adds_lessons_using_project_default_pipeline(): void
    {
        Queue::fake();

        [$viewer, , $project] = $this->createViewerFolderAndProject();
        $pipelineVersion = $this->createPipelineVersion();
        $project->update([
            'default_pipeline_version_id' => $pipelineVersion->id,
        ]);

        Video2BookServer::actingAs($viewer)
            ->tool(AddProjectLessonsFromListTool::class, [
                'project_id' => $project->id,
                'lessons_list' => "Урок 1\nhttps://www.youtube.com/watch?v=video1\n\nУрок 2\nhttps://www.youtube.com/watch?v=video2",
            ])
            ->assertOk()
            ->assertSee(['Урок 1', 'Урок 2']);

        $this->assertDatabaseHas('lessons', [
            'project_id' => $project->id,
            'name' => 'Урок 1',
        ]);
        $this->assertDatabaseHas('lessons', [
            'project_id' => $project->id,
            'name' => 'Урок 2',
        ]);
        Queue::assertPushed(DownloadLessonAudioJob::class, 2);
    }

    public function test_list_lesson_runs_tool_returns_runs_for_lesson(): void
    {
        [$viewer, , $project] = $this->createViewerFolderAndProject();
        [$pipeline, $pipelineVersion, $stepVersions] = $this->createPipelineVersionWithSteps([
            ['name' => 'Транскрибация', 'type' => 'transcribe'],
            ['name' => 'Текст', 'type' => 'text'],
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок с прогонами',
            'tag' => 'default',
            'source_filename' => 'lessons/1.mp3',
            'settings' => [],
        ]);
        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'done',
            'state' => [],
        ]);

        PipelineRunStep::query()->create([
            'pipeline_run_id' => $run->id,
            'step_version_id' => $stepVersions[0]->id,
            'position' => 1,
            'status' => 'done',
        ]);
        PipelineRunStep::query()->create([
            'pipeline_run_id' => $run->id,
            'step_version_id' => $stepVersions[1]->id,
            'position' => 2,
            'status' => 'done',
        ]);

        Video2BookServer::actingAs($viewer)
            ->tool(ListLessonRunsTool::class, [
                'lesson_id' => $lesson->id,
            ])
            ->assertOk()
            ->assertSee([
                'Урок с прогонами',
                'Пайплайн • v1',
                'done',
            ]);
    }

    public function test_list_pipeline_templates_tool_returns_current_active_versions(): void
    {
        $viewer = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_ADMIN,
        ]);

        $pipelineVersion = $this->createPipelineVersion();
        $pipelineVersion->update([
            'description' => 'Описание шаблона',
        ]);
        $stepVersionId = (int) $pipelineVersion->versionSteps()->value('step_version_id');
        $stepVersion = StepVersion::query()->findOrFail($stepVersionId);
        $stepVersion->update([
            'description' => 'Описание шага',
            'settings' => [
                ...((array) $stepVersion->settings),
                'is_default' => true,
            ],
        ]);

        Video2BookServer::actingAs($viewer)
            ->tool(ListPipelineTemplatesTool::class)
            ->assertOk()
            ->assertStructuredContent([
                'pipeline_versions' => [
                    [
                        'id' => $pipelineVersion->id,
                        'name' => 'Pipeline title',
                        'label' => 'Pipeline title • v1',
                        'description' => 'Описание шаблона',
                        'version' => 1,
                        'steps' => [
                            [
                                'id' => $stepVersionId,
                                'position' => 1,
                                'name' => 'Шаг 1',
                                'description' => 'Описание шага',
                                'is_default' => true,
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function test_list_run_steps_and_get_run_step_result_tools_return_run_details(): void
    {
        [$viewer, , $project] = $this->createViewerFolderAndProject();
        [, $pipelineVersion, $stepVersions] = $this->createPipelineVersionWithSteps([
            ['name' => 'Шаг 1', 'type' => 'transcribe'],
            ['name' => 'Шаг 2', 'type' => 'text'],
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок шага',
            'tag' => 'default',
            'source_filename' => 'lessons/step.mp3',
            'settings' => [],
        ]);
        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'running',
            'state' => [],
        ]);

        $firstStep = PipelineRunStep::query()->create([
            'pipeline_run_id' => $run->id,
            'step_version_id' => $stepVersions[0]->id,
            'position' => 1,
            'status' => 'done',
            'result' => 'Транскрипт',
            'input_tokens' => 10,
            'output_tokens' => 20,
            'cost' => 0.123,
        ]);
        $secondStep = PipelineRunStep::query()->create([
            'pipeline_run_id' => $run->id,
            'step_version_id' => $stepVersions[1]->id,
            'position' => 2,
            'status' => 'running',
            'result' => 'Итоговый текст',
            'error' => 'Предупреждение',
            'input_tokens' => 30,
            'output_tokens' => 40,
            'cost' => 0.456,
        ]);

        Video2BookServer::actingAs($viewer)
            ->tool(ListRunStepsTool::class, [
                'run_id' => $run->id,
            ])
            ->assertOk()
            ->assertSee(['Шаг 1', 'Шаг 2', 'running']);

        Video2BookServer::actingAs($viewer)
            ->tool(GetRunStepResultTool::class, [
                'run_id' => $run->id,
                'step_id' => $secondStep->id,
            ])
            ->assertOk()
            ->assertSee(['Итоговый текст', 'Предупреждение']);
    }

    public function test_restart_run_step_tool_resets_selected_and_following_steps_and_dispatches_job(): void
    {
        Queue::fake();

        [$viewer, , $project] = $this->createViewerFolderAndProject();
        [, $pipelineVersion, $stepVersions] = $this->createPipelineVersionWithSteps([
            ['name' => 'Шаг 1', 'type' => 'transcribe'],
            ['name' => 'Шаг 2', 'type' => 'text'],
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок перезапуска',
            'tag' => 'default',
            'source_filename' => 'lessons/restart.mp3',
            'settings' => [],
        ]);
        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'failed',
            'state' => [],
        ]);

        PipelineRunStep::query()->create([
            'pipeline_run_id' => $run->id,
            'step_version_id' => $stepVersions[0]->id,
            'position' => 1,
            'status' => 'done',
            'result' => 'Готово',
        ]);
        $restartStep = PipelineRunStep::query()->create([
            'pipeline_run_id' => $run->id,
            'step_version_id' => $stepVersions[1]->id,
            'position' => 2,
            'status' => 'failed',
            'result' => 'Старый результат',
            'error' => 'Ошибка',
            'input_tokens' => 1,
            'output_tokens' => 2,
            'cost' => 0.100,
        ]);

        Video2BookServer::actingAs($viewer)
            ->tool(RestartRunStepTool::class, [
                'run_id' => $run->id,
                'step_id' => $restartStep->id,
            ])
            ->assertOk()
            ->assertSee(['queued', 'pending']);

        Queue::assertPushedOn(ProcessPipelineJob::QUEUE, ProcessPipelineJob::class);
        $this->assertSame('queued', $run->fresh()->status);
        $this->assertSame('pending', $restartStep->fresh()->status);
        $this->assertNull($restartStep->fresh()->result);
        $this->assertNull($restartStep->fresh()->error);
    }

    public function test_list_queue_tasks_tool_returns_queue_snapshot(): void
    {
        [$viewer, , $project] = $this->createViewerFolderAndProject('Проект очереди');
        [$pipeline, $pipelineVersion, $stepVersions] = $this->createPipelineVersionWithSteps([
            ['name' => 'Шаг 1', 'type' => 'transcribe'],
            ['name' => 'Шаг 2', 'type' => 'text'],
        ], 'Пайплайн очереди');

        $pipelineLesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок обработки',
            'tag' => 'default',
            'settings' => [],
            'source_filename' => null,
        ]);
        $pipelineRun = PipelineRun::query()->create([
            'lesson_id' => $pipelineLesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'running',
            'state' => [],
        ]);

        PipelineRunStep::query()->create([
            'pipeline_run_id' => $pipelineRun->id,
            'step_version_id' => $stepVersions[0]->id,
            'position' => 1,
            'status' => 'done',
        ]);
        PipelineRunStep::query()->create([
            'pipeline_run_id' => $pipelineRun->id,
            'step_version_id' => $stepVersions[1]->id,
            'position' => 2,
            'status' => 'running',
        ]);

        $downloadLesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок скачивания',
            'tag' => 'default',
            'settings' => [
                'download_status' => 'running',
                'download_progress' => 42.5,
            ],
            'source_filename' => null,
        ]);
        $downloadRun = PipelineRun::query()->create([
            'lesson_id' => $downloadLesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'queued',
            'state' => [],
        ]);

        PipelineRunStep::query()->create([
            'pipeline_run_id' => $downloadRun->id,
            'step_version_id' => $stepVersions[0]->id,
            'position' => 1,
            'status' => 'done',
        ]);
        PipelineRunStep::query()->create([
            'pipeline_run_id' => $downloadRun->id,
            'step_version_id' => $stepVersions[1]->id,
            'position' => 2,
            'status' => 'pending',
        ]);

        DB::table('jobs')->insert([
            [
                'queue' => ProcessPipelineJob::QUEUE,
                'payload' => json_encode([
                    'displayName' => ProcessPipelineJob::class,
                    'data' => [
                        'commandName' => ProcessPipelineJob::class,
                        'command' => 's:13:"pipelineRunId";i:'.$pipelineRun->id.';',
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'attempts' => 0,
                'reserved_at' => now()->timestamp,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ],
            [
                'queue' => DownloadLessonAudioJob::QUEUE,
                'payload' => json_encode([
                    'displayName' => DownloadLessonAudioJob::class,
                    'data' => [
                        'commandName' => DownloadLessonAudioJob::class,
                        'command' => 's:8:"lessonId";i:'.$downloadLesson->id.';',
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ],
        ]);

        Video2BookServer::actingAs($viewer)
            ->tool(ListQueueTasksTool::class)
            ->assertOk()
            ->assertSee([
                'Очередь обработки',
                'Урок обработки',
                'Урок скачивания',
                'Пайплайн очереди • v1',
            ]);
    }

    /**
     * @return array{0:User,1:Folder,2:Project}
     */
    private function createViewerFolderAndProject(string $projectName = 'Проект'): array
    {
        $this->ensureDefaultProjectTag();

        $viewer = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_ADMIN,
        ]);
        $folder = Folder::query()->create([
            'name' => 'Рабочая папка '.str()->random(6),
            'hidden' => false,
            'visible_for' => [],
        ]);
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => $projectName,
            'tags' => null,
            'settings' => [],
        ]);

        return [$viewer, $folder, $project];
    }

    private function ensureDefaultProjectTag(): void
    {
        ProjectTag::query()->firstOrCreate([
            'slug' => 'default',
        ], [
            'description' => null,
        ]);
    }

    private function createPipelineVersion(string $title = 'Pipeline title'): PipelineVersion
    {
        $pipeline = Pipeline::query()->create();
        $version = $pipeline->versions()->create([
            'version' => 1,
            'title' => $title,
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);
        $pipeline->update([
            'current_version_id' => $version->id,
        ]);

        $step = Step::query()->create([
            'pipeline_id' => $pipeline->id,
            'current_version_id' => null,
        ]);
        $stepVersion = StepVersion::query()->create([
            'step_id' => $step->id,
            'input_step_id' => null,
            'type' => 'text',
            'version' => 1,
            'name' => 'Шаг 1',
            'description' => null,
            'prompt' => 'Prompt',
            'settings' => [
                'provider' => 'openai',
                'model' => 'gpt-4.1-mini',
            ],
            'status' => 'active',
        ]);
        $step->update([
            'current_version_id' => $stepVersion->id,
        ]);
        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $version->id,
            'step_version_id' => $stepVersion->id,
            'position' => 1,
        ]);

        return $version;
    }

    /**
     * @param  array<int, array{name:string,type:string}>  $steps
     * @return array{0:Pipeline,1:PipelineVersion,2:array<int, StepVersion>}
     */
    private function createPipelineVersionWithSteps(array $steps, string $title = 'Пайплайн'): array
    {
        $pipeline = Pipeline::query()->create();
        $version = $pipeline->versions()->create([
            'version' => 1,
            'title' => $title,
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);
        $pipeline->update(['current_version_id' => $version->id]);

        $stepVersions = [];

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

            $stepVersions[] = $stepVersion;
        }

        return [$pipeline, $version, $stepVersions];
    }
}
