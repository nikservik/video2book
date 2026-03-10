<?php

namespace Tests\Unit\Mcp\Runs;

use App\Jobs\ProcessPipelineJob;
use App\Mcp\Servers\Video2BookServer;
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
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunsToolsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_list_lesson_runs_tool_returns_runs_for_lesson(): void
    {
        $viewer = $this->makeUser();
        [, $lesson, $run] = $this->createLessonWithRun();

        Video2BookServer::actingAs($viewer)
            ->tool(ListLessonRunsTool::class, [
                'lesson_id' => $lesson->id,
            ])
            ->assertOk()
            ->assertSee([(string) $run->id, $lesson->name]);
    }

    public function test_list_pipeline_templates_tool_returns_available_versions(): void
    {
        $viewer = $this->makeUser();
        [, $pipelineVersion, $stepVersion] = $this->createPipelineVersionWithTextStep();
        $pipelineVersion->update([
            'description' => 'Описание шаблона',
        ]);
        $stepVersion->update([
            'description' => 'Описание шага',
        ]);

        Video2BookServer::actingAs($viewer)
            ->tool(ListPipelineTemplatesTool::class)
            ->assertOk()
            ->assertStructuredContent([
                'pipeline_versions' => [
                    [
                        'id' => $pipelineVersion->id,
                        'name' => 'Pipeline',
                        'label' => 'Pipeline • v1',
                        'description' => 'Описание шаблона',
                        'version' => 1,
                        'steps' => [
                            [
                                'id' => $stepVersion->id,
                                'position' => 1,
                                'name' => 'Summary',
                                'description' => 'Описание шага',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function test_list_run_steps_tool_returns_steps_for_run(): void
    {
        $viewer = $this->makeUser();
        [, , $run] = $this->createLessonWithRun();

        Video2BookServer::actingAs($viewer)
            ->tool(ListRunStepsTool::class, [
                'run_id' => $run->id,
            ])
            ->assertOk()
            ->assertSee(['Summary', 'done']);
    }

    public function test_get_run_step_result_tool_returns_selected_step_result(): void
    {
        $viewer = $this->makeUser();
        [, , $run, $step] = $this->createLessonWithRun(includeStepReference: true);

        Video2BookServer::actingAs($viewer)
            ->tool(GetRunStepResultTool::class, [
                'run_id' => $run->id,
                'step_id' => $step->id,
            ])
            ->assertOk()
            ->assertSee(['# Result', 'Summary']);
    }

    public function test_restart_run_step_tool_resets_step_and_requeues_run(): void
    {
        Queue::fake();

        $viewer = $this->makeUser();
        [, , $run, $step] = $this->createLessonWithRun(includeStepReference: true);

        Video2BookServer::actingAs($viewer)
            ->tool(RestartRunStepTool::class, [
                'run_id' => $run->id,
                'step_id' => $step->id,
            ])
            ->assertOk()
            ->assertSee('queued');

        $run->refresh();
        $step->refresh();

        $this->assertSame('queued', $run->status);
        $this->assertSame('pending', $step->status);
        $this->assertNull($step->result);

        Queue::assertPushedOn(ProcessPipelineJob::QUEUE, ProcessPipelineJob::class);
    }

    private function makeUser(int $accessLevel = User::ACCESS_LEVEL_ADMIN): User
    {
        return User::factory()->create([
            'access_token' => (string) Str::uuid(),
            'access_level' => $accessLevel,
        ]);
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

    /**
     * @return array{0: Project, 1: Lesson, 2: PipelineRun, 3?: PipelineRunStep}
     */
    private function createLessonWithRun(bool $includeStepReference = false): array
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
            'settings' => [],
        ]);
        [, $pipelineVersion, $textStepVersion] = $this->createPipelineVersionWithTextStep();
        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'done',
            'state' => [],
        ]);
        $step = PipelineRunStep::query()->create([
            'pipeline_run_id' => $run->id,
            'step_version_id' => $textStepVersion->id,
            'position' => 1,
            'status' => 'done',
            'result' => '# Result',
        ]);

        return $includeStepReference
            ? [$project, $lesson, $run, $step]
            : [$project, $lesson, $run];
    }
}
