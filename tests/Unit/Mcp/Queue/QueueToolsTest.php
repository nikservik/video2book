<?php

namespace Tests\Unit\Mcp\Queue;

use App\Jobs\DownloadLessonAudioJob;
use App\Jobs\ProcessPipelineJob;
use App\Mcp\Servers\Video2BookServer;
use App\Mcp\Tools\Queue\ListQueueTasksTool;
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
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueToolsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_list_queue_tasks_tool_returns_current_queue_snapshot(): void
    {
        $viewer = $this->makeUser();
        [, $lesson, $run] = $this->createQueuedRun();

        DB::table('jobs')->insert([
            [
                'queue' => ProcessPipelineJob::QUEUE,
                'payload' => json_encode([
                    'displayName' => ProcessPipelineJob::class,
                    'data' => [
                        'commandName' => ProcessPipelineJob::class,
                        'command' => 'O:0:"":1:{s:13:"pipelineRunId";i:'.$run->id.';}',
                    ],
                ], JSON_THROW_ON_ERROR),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ],
            [
                'queue' => DownloadLessonAudioJob::QUEUE,
                'payload' => json_encode([
                    'displayName' => DownloadLessonAudioJob::class,
                    'data' => [
                        'commandName' => DownloadLessonAudioJob::class,
                        'command' => 'O:0:"":1:{s:8:"lessonId";i:'.$lesson->id.';}',
                    ],
                ], JSON_THROW_ON_ERROR),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ],
        ]);

        Video2BookServer::actingAs($viewer)
            ->tool(ListQueueTasksTool::class)
            ->assertOk()
            ->assertSee(['Очередь обработки', $lesson->name, 'Pipeline']);
    }

    private function makeUser(int $accessLevel = User::ACCESS_LEVEL_ADMIN): User
    {
        return User::factory()->create([
            'access_token' => (string) Str::uuid(),
            'access_level' => $accessLevel,
        ]);
    }

    /**
     * @return array{0: Project, 1: Lesson, 2: PipelineRun}
     */
    private function createQueuedRun(): array
    {
        ProjectTag::query()->firstOrCreate(['slug' => 'default'], ['description' => null]);
        $folder = Folder::query()->create([
            'name' => 'Folder '.Str::random(6),
            'hidden' => false,
            'visible_for' => [],
        ]);
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Project',
            'tags' => null,
        ]);
        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => ['download_progress' => 50],
        ]);
        $pipeline = Pipeline::query()->create();
        $pipelineVersion = PipelineVersion::query()->create([
            'pipeline_id' => $pipeline->id,
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
        $stepVersion = StepVersion::query()->create([
            'step_id' => $step->id,
            'input_step_id' => null,
            'name' => 'Summary',
            'type' => 'text',
            'version' => 1,
            'description' => null,
            'prompt' => 'Prompt',
            'settings' => ['provider' => 'openai', 'model' => 'gpt-4o-mini'],
            'status' => 'active',
        ]);
        $step->update(['current_version_id' => $stepVersion->id]);
        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $pipelineVersion->id,
            'step_version_id' => $stepVersion->id,
            'position' => 1,
        ]);
        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'queued',
            'state' => [],
        ]);
        PipelineRunStep::query()->create([
            'pipeline_run_id' => $run->id,
            'step_version_id' => $stepVersion->id,
            'position' => 1,
            'status' => 'pending',
        ]);

        return [$project, $lesson, $run];
    }
}
