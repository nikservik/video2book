<?php

namespace Tests\Feature;

use App\Jobs\ProcessPipelineJob;
use App\Livewire\ProjectRunPage;
use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\Project;
use App\Models\ProjectTag;
use App\Models\Step;
use App\Models\StepVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectRunPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_run_page_renders_headers_and_steps_sidebar(): void
    {
        [$project, $pipelineRun, $firstRunStep] = $this->createProjectRunWithSteps();

        $response = $this->get(route('projects.runs.show', [
            'project' => $project,
            'pipelineRun' => $pipelineRun,
        ]));

        $response
            ->assertStatus(200)
            ->assertSee('Урок по Laravel')
            ->assertSee('Пайплайн обучения • v3')
            ->assertDontSee('data-project-actions-toggle', false)
            ->assertDontSee('data-project-actions-menu', false)
            ->assertSee('isActionsMenuOpen', false)
            ->assertSee('data-mobile-run-steps-dropdown', false)
            ->assertSee('data-mobile-run-steps-toggle', false)
            ->assertSee('data-mobile-run-steps-list', false)
            ->assertSee('data-run-step-mobile="'.$firstRunStep->id.'"', false)
            ->assertSee('hidden md:block md:col-span-1', false)
            ->assertSee('grid grid-cols-1 gap-6 md:grid-cols-3', false)
            ->assertSee('wire:poll.2s="refreshRunSteps"', false)
            ->assertDontSee('wire:poll.1s="refreshSelectedStepResult"', false)
            ->assertSee('Транскрибация')
            ->assertSee('Саммаризация')
            ->assertSee('<h2>Заголовок первого шага</h2>', false)
            ->assertSee('<li>Пункт 1</li>', false)
            ->assertSee('Готово')
            ->assertSee('Обработка')
            ->assertSee('DOCX')
            ->assertDontSee('data-run-step-pointer="true"', false)
            ->assertSee('i:1,234')
            ->assertSee('o:56,789')
            ->assertSee('$1.235')
            ->assertSee('data-selected-step-id="'.$firstRunStep->id.'"', false);
    }

    public function test_project_run_page_for_zero_access_level_user_shows_step_number_prefix_and_hides_metrics(): void
    {
        [$project, $pipelineRun] = $this->createProjectRunWithSteps();

        $user = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_USER,
            'access_token' => (string) Str::uuid(),
        ]);

        $response = $this
            ->withCookie((string) config('simple_auth.cookie_name'), (string) $user->access_token)
            ->get(route('projects.runs.show', [
                'project' => $project,
                'pipelineRun' => $pipelineRun,
            ]));

        $response
            ->assertStatus(200)
            ->assertSee('Готово')
            ->assertSee('Обработка')
            ->assertDontSee('i:1,234')
            ->assertDontSee('o:56,789')
            ->assertDontSee('$1.235')
            ->assertDontSee('i:321')
            ->assertDontSee('o:654')
            ->assertDontSee('$0.045');

        $response->assertSeeInOrder([
            'Шаг 1.',
            'Транскрибация',
        ]);
        $response->assertSeeInOrder([
            'Шаг 2.',
            'Саммаризация',
        ]);

        $this->assertSame(1, substr_count($response->getContent(), 'data-run-step-pointer="true"'));
        $this->assertSame(1, substr_count($response->getContent(), 'data-run-step-pointer="false"'));
    }

    public function test_project_run_page_polls_selected_step_result_only_for_running_step(): void
    {
        [$project, $pipelineRun, $firstRunStep, $secondRunStep] = $this->createProjectRunWithSteps();

        Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun,
        ])
            ->assertSet('selectedStepId', $firstRunStep->id)
            ->assertDontSee('wire:poll.1s="refreshSelectedStepResult"', false)
            ->call('selectStep', $secondRunStep->id)
            ->assertSet('selectedStepId', $secondRunStep->id)
            ->assertSee('wire:poll.1s="refreshSelectedStepResult"', false);
    }

    public function test_project_run_page_hides_steps_polling_when_all_steps_done(): void
    {
        [$project, $pipelineRun, , $secondRunStep] = $this->createProjectRunWithSteps();

        $secondRunStep->update(['status' => 'done']);
        $pipelineRun->update(['status' => 'done']);

        $this->get(route('projects.runs.show', [
            'project' => $project,
            'pipelineRun' => $pipelineRun->fresh(),
        ]))
            ->assertStatus(200)
            ->assertDontSee('wire:poll.2s="refreshRunSteps"', false);
    }

    public function test_project_run_page_can_switch_active_step(): void
    {
        [$project, $pipelineRun, $firstRunStep, $secondRunStep] = $this->createProjectRunWithSteps();

        Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun,
        ])
            ->assertSet('selectedStepId', $firstRunStep->id)
            ->assertSee('<h2>Заголовок первого шага</h2>', false)
            ->assertSee('data-selected-step-id="'.$firstRunStep->id.'"', false)
            ->call('selectStep', $secondRunStep->id)
            ->assertSet('selectedStepId', $secondRunStep->id)
            ->assertSee('<h3>Заголовок второго шага</h3>', false)
            ->assertSee('data-selected-step-id="'.$secondRunStep->id.'"', false);
    }

    public function test_project_run_page_selects_last_done_step_on_initial_load(): void
    {
        [$project, $pipelineRun, , $secondRunStep] = $this->createProjectRunWithSteps();

        $secondRunStep->update(['status' => 'done']);

        PipelineRunStep::query()->create([
            'pipeline_run_id' => $pipelineRun->id,
            'step_version_id' => $secondRunStep->step_version_id,
            'position' => 3,
            'status' => 'running',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cost' => 0,
            'result' => null,
            'error' => null,
            'start_time' => null,
            'end_time' => null,
        ]);

        Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun->fresh(),
        ])
            ->assertSet('selectedStepId', $secondRunStep->id)
            ->assertSee('data-selected-step-id="'.$secondRunStep->id.'"', false);
    }

    public function test_project_run_page_selects_default_step_when_it_is_done(): void
    {
        [$project, $pipelineRun, $firstRunStep, $secondRunStep] = $this->createProjectRunWithSteps();

        $secondRunStep->update(['status' => 'done']);

        $firstStepVersion = StepVersion::query()->findOrFail($firstRunStep->step_version_id);
        $firstStepVersion->update([
            'settings' => [
                ...((array) $firstStepVersion->settings),
                'is_default' => true,
            ],
        ]);

        Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun->fresh(),
        ])
            ->assertSet('selectedStepId', $firstRunStep->id)
            ->assertSee('data-selected-step-id="'.$firstRunStep->id.'"', false);
    }

    public function test_project_run_page_selects_last_done_step_when_default_step_is_not_done(): void
    {
        [$project, $pipelineRun, $firstRunStep, $secondRunStep] = $this->createProjectRunWithSteps();

        $secondStepVersion = StepVersion::query()->findOrFail($secondRunStep->step_version_id);
        $secondStepVersion->update([
            'settings' => [
                ...((array) $secondStepVersion->settings),
                'is_default' => true,
            ],
        ]);

        Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun->fresh(),
        ])
            ->assertSet('selectedStepId', $firstRunStep->id)
            ->assertSee('data-selected-step-id="'.$firstRunStep->id.'"', false);
    }

    public function test_project_run_page_without_default_flag_selects_last_step_when_no_steps_done(): void
    {
        [$project, $pipelineRun, $firstRunStep, $secondRunStep] = $this->createProjectRunWithSteps();

        $firstRunStep->update(['status' => 'pending']);

        Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun->fresh(),
        ])
            ->assertSet('selectedStepId', $secondRunStep->id)
            ->assertSee('data-selected-step-id="'.$secondRunStep->id.'"', false);
    }

    public function test_project_run_page_can_toggle_result_preview_and_source_modes(): void
    {
        [$project, $pipelineRun] = $this->createProjectRunWithSteps();

        Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun,
        ])
            ->assertSet('resultViewMode', 'preview')
            ->assertSee('data-result-mode="preview"', false)
            ->assertSee('<h2>Заголовок первого шага</h2>', false)
            ->call('setResultViewMode', 'source')
            ->assertSet('resultViewMode', 'source')
            ->assertSee('data-result-mode="source"', false)
            ->assertSee('## Заголовок первого шага')
            ->assertSee('- Пункт 1')
            ->call('setResultViewMode', 'preview')
            ->assertSet('resultViewMode', 'preview')
            ->assertSee('data-result-mode="preview"', false)
            ->assertSee('<h2>Заголовок первого шага</h2>', false);
    }

    public function test_project_run_page_can_download_selected_step_pdf_markdown_and_docx(): void
    {
        [$project, $pipelineRun] = $this->createProjectRunWithSteps();

        $pdfFilename = Str::slug('Урок по Laravel-Транскрибация', '_').'.pdf';
        $markdownFilename = Str::slug('Урок по Laravel-Саммаризация', '_').'.md';
        $docxFilename = Str::slug('Урок по Laravel-Транскрибация', '_').'.docx';

        Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun,
        ])
            ->call('downloadSelectedStepPdf')
            ->assertFileDownloaded($pdfFilename, contentType: 'application/pdf')
            ->call('downloadSelectedStepDocx')
            ->assertFileDownloaded($docxFilename, contentType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->call('selectStep', $pipelineRun->steps()->where('position', 2)->firstOrFail()->id)
            ->call('downloadSelectedStepMarkdown')
            ->assertFileDownloaded($markdownFilename, contentType: 'text/markdown; charset=UTF-8');
    }

    public function test_project_run_page_can_restart_from_selected_step(): void
    {
        Queue::fake();

        [$project, $pipelineRun, $firstRunStep, $secondRunStep] = $this->createProjectRunWithSteps();

        Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun,
        ])
            ->assertSet('selectedStepId', $firstRunStep->id)
            ->call('selectStep', $secondRunStep->id)
            ->assertSet('selectedStepId', $secondRunStep->id)
            ->call('restartSelectedStep')
            ->assertSet('selectedStepId', $secondRunStep->id);

        $this->assertSame('queued', $pipelineRun->fresh()->status);
        $this->assertSame('done', $firstRunStep->fresh()->status);
        $this->assertSame("## Заголовок первого шага\n\n- Пункт 1\n- Пункт 2", $firstRunStep->fresh()->result);
        $this->assertSame('pending', $secondRunStep->fresh()->status);
        $this->assertNull($secondRunStep->fresh()->result);
        $this->assertNull($secondRunStep->fresh()->error);
        $this->assertNull($secondRunStep->fresh()->start_time);
        $this->assertNull($secondRunStep->fresh()->end_time);

        Queue::assertPushedOn(ProcessPipelineJob::QUEUE, ProcessPipelineJob::class, function (ProcessPipelineJob $job) use ($pipelineRun): bool {
            return $job->pipelineRunId === $pipelineRun->id;
        });
    }

    public function test_project_run_page_keeps_steps_order_after_selecting_step(): void
    {
        [$project, $pipelineRun, $firstRunStep, $secondRunStep] = $this->createProjectRunWithSteps();

        $firstRunStep->update(['position' => 2]);
        $secondRunStep->update(['position' => 1]);

        $component = Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun->fresh(),
        ]);

        $this->assertTextOrder($component->html(), [
            'data-run-step="'.$secondRunStep->id.'"',
            'data-run-step="'.$firstRunStep->id.'"',
        ]);

        $component->call('selectStep', $firstRunStep->id);

        $this->assertTextOrder($component->html(), [
            'data-run-step="'.$secondRunStep->id.'"',
            'data-run-step="'.$firstRunStep->id.'"',
        ]);
    }

    public function test_project_run_page_shows_control_buttons_based_on_steps_state(): void
    {
        [$project, $pipelineRun, , $secondRunStep] = $this->createProjectRunWithSteps();

        $this->get(route('projects.runs.show', [
            'project' => $project,
            'pipelineRun' => $pipelineRun->fresh(),
        ]))
            ->assertStatus(200)
            ->assertDontSee('data-run-control="start"', false)
            ->assertDontSee('data-run-control="pause"', false)
            ->assertSee('data-run-control="stop"', false);

        $secondRunStep->update(['status' => 'paused']);
        $pipelineRun->update(['status' => 'paused']);

        $this->get(route('projects.runs.show', [
            'project' => $project,
            'pipelineRun' => $pipelineRun->fresh(),
        ]))
            ->assertStatus(200)
            ->assertSee('wire:poll.1s="refreshRunControls"', false)
            ->assertSee('data-run-control="start"', false)
            ->assertDontSee('data-run-control="pause"', false)
            ->assertDontSee('data-run-control="stop"', false);

        $secondRunStep->update(['status' => 'pending']);
        $pipelineRun->update(['status' => 'queued']);

        $this->get(route('projects.runs.show', [
            'project' => $project,
            'pipelineRun' => $pipelineRun->fresh(),
        ]))
            ->assertStatus(200)
            ->assertDontSee('data-run-control="start"', false)
            ->assertSee('data-run-control="pause"', false)
            ->assertSee('data-run-control="stop"', false);

        $secondRunStep->update(['status' => 'failed']);
        $pipelineRun->steps()->where('position', '>', $secondRunStep->position)->update(['status' => 'pending']);
        $pipelineRun->update(['status' => 'failed']);

        $this->get(route('projects.runs.show', [
            'project' => $project,
            'pipelineRun' => $pipelineRun->fresh(),
        ]))
            ->assertStatus(200)
            ->assertSee('data-run-control="start"', false)
            ->assertDontSee('data-run-control="pause"', false)
            ->assertDontSee('data-run-control="stop"', false);
    }

    public function test_project_run_page_can_pause_stop_and_start_run(): void
    {
        Queue::fake();

        [$project, $pipelineRun, $firstRunStep, $secondRunStep] = $this->createProjectRunWithSteps();

        $thirdRunStep = PipelineRunStep::query()->create([
            'pipeline_run_id' => $pipelineRun->id,
            'step_version_id' => $secondRunStep->step_version_id,
            'position' => 3,
            'status' => 'pending',
            'input_tokens' => null,
            'output_tokens' => null,
            'cost' => null,
            'result' => null,
            'error' => null,
            'start_time' => null,
            'end_time' => null,
        ]);

        Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun->fresh(),
        ])
            ->call('pauseRun');

        $this->assertSame('running', $pipelineRun->fresh()->status);
        $this->assertSame('running', $secondRunStep->fresh()->status);
        $this->assertSame('paused', $thirdRunStep->fresh()->status);

        Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun->fresh(),
        ])
            ->call('stopRun');

        $this->assertSame('paused', $pipelineRun->fresh()->status);
        $this->assertSame('paused', $secondRunStep->fresh()->status);
        $this->assertSame('paused', $thirdRunStep->fresh()->status);
        $this->assertTrue((bool) data_get($pipelineRun->fresh()->state, 'stop_requested'));

        Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun->fresh(),
        ])
            ->call('startRun');

        $this->assertSame('queued', $pipelineRun->fresh()->status);
        $this->assertSame('done', $firstRunStep->fresh()->status);
        $this->assertSame('pending', $secondRunStep->fresh()->status);
        $this->assertSame('pending', $thirdRunStep->fresh()->status);
        $this->assertNull(data_get($pipelineRun->fresh()->state, 'stop_requested'));

        Queue::assertPushedOn(ProcessPipelineJob::QUEUE, ProcessPipelineJob::class, function (ProcessPipelineJob $job) use ($pipelineRun): bool {
            return $job->pipelineRunId === $pipelineRun->id;
        });
    }

    /**
     * @return array{0: Project, 1: PipelineRun, 2: PipelineRunStep, 3: PipelineRunStep}
     */
    private function createProjectRunWithSteps(): array
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект Ран',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок по Laravel',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        $pipeline = Pipeline::query()->create();
        $pipelineVersion = $pipeline->versions()->create([
            'version' => 3,
            'title' => 'Пайплайн обучения',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);

        $pipelineRun = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'running',
            'state' => [],
        ]);

        $stepOne = Step::query()->create([
            'pipeline_id' => $pipeline->id,
            'current_version_id' => null,
        ]);

        $stepOneVersion = StepVersion::query()->create([
            'step_id' => $stepOne->id,
            'input_step_id' => null,
            'name' => 'Транскрибация',
            'type' => 'transcribe',
            'version' => 1,
            'description' => null,
            'prompt' => null,
            'settings' => [],
            'status' => 'active',
        ]);

        $stepOne->update(['current_version_id' => $stepOneVersion->id]);

        $stepTwo = Step::query()->create([
            'pipeline_id' => $pipeline->id,
            'current_version_id' => null,
        ]);

        $stepTwoVersion = StepVersion::query()->create([
            'step_id' => $stepTwo->id,
            'input_step_id' => $stepOne->id,
            'name' => 'Саммаризация',
            'type' => 'text',
            'version' => 1,
            'description' => null,
            'prompt' => null,
            'settings' => [],
            'status' => 'active',
        ]);

        $stepTwo->update(['current_version_id' => $stepTwoVersion->id]);

        $firstRunStep = PipelineRunStep::query()->create([
            'pipeline_run_id' => $pipelineRun->id,
            'step_version_id' => $stepOneVersion->id,
            'position' => 1,
            'status' => 'done',
            'input_tokens' => 1234,
            'output_tokens' => 56789,
            'cost' => 1.2346,
            'result' => "## Заголовок первого шага\n\n- Пункт 1\n- Пункт 2",
            'error' => null,
            'start_time' => null,
            'end_time' => null,
        ]);

        $secondRunStep = PipelineRunStep::query()->create([
            'pipeline_run_id' => $pipelineRun->id,
            'step_version_id' => $stepTwoVersion->id,
            'position' => 2,
            'status' => 'running',
            'input_tokens' => 321,
            'output_tokens' => 654,
            'cost' => 0.045,
            'result' => "### Заголовок второго шага\n\n1. Элемент 1\n2. Элемент 2",
            'error' => null,
            'start_time' => null,
            'end_time' => null,
        ]);

        return [$project, $pipelineRun, $firstRunStep, $secondRunStep];
    }

    /**
     * @param  array<int, string>  $orderedTexts
     */
    private function assertTextOrder(string $html, array $orderedTexts): void
    {
        $previousPosition = -1;

        foreach ($orderedTexts as $text) {
            $currentPosition = strpos($html, $text);

            $this->assertNotFalse($currentPosition, sprintf('Text "%s" was not found in html.', $text));
            $this->assertGreaterThan(
                $previousPosition,
                $currentPosition,
                sprintf('Text "%s" appears in invalid order.', $text)
            );

            $previousPosition = $currentPosition;
        }
    }
}
