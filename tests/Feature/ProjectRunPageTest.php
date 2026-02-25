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
use App\Support\StepResultHtmlToMarkdownConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mews\Purifier\Facades\Purifier;
use Spatie\Activitylog\Models\Activity;
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
            ->assertSee('data-editor-state="disabled"', false)
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
            ->assertSee('Пайплайн обучения')
            ->assertDontSee('Пайплайн обучения • v3')
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

        $component = Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun,
        ]);

        $initialEditorHtml = $component->instance()->selectedStepEditorHtml;

        $component
            ->assertSet('selectedStepId', $firstRunStep->id)
            ->assertSee('data-selected-step-id="'.$firstRunStep->id.'"', false)
            ->call('selectStep', $secondRunStep->id)
            ->assertSet('selectedStepId', $secondRunStep->id)
            ->assertSee('data-selected-step-id="'.$secondRunStep->id.'"', false);

        $this->assertStringContainsString('<h2>Заголовок первого шага</h2>', $initialEditorHtml);
        $this->assertStringContainsString('<h3>Заголовок второго шага</h3>', $component->instance()->selectedStepEditorHtml);
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

    public function test_project_run_page_shows_only_preview_mode_for_step_result(): void
    {
        [$project, $pipelineRun] = $this->createProjectRunWithSteps();

        Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun,
        ])
            ->assertSee('data-result-mode="preview"', false)
            ->assertSee('data-editor-state="disabled"', false)
            ->assertDontSee('data-result-view="preview"', false)
            ->assertDontSee('data-result-view="source"', false)
            ->assertDontSee('data-result-mode="source"', false)
            ->assertDontSee('Исходник')
            ->assertDontSee('Превью');
    }

    public function test_project_run_page_can_switch_result_block_into_edit_mode_with_trix_toolbar(): void
    {
        [$project, $pipelineRun, , $secondRunStep] = $this->createProjectRunWithSteps();

        Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun,
        ])
            ->call('selectStep', $secondRunStep->id)
            ->assertSee('wire:poll.1s="refreshSelectedStepResult"', false)
            ->assertSet('isEditingSelectedStepResult', false)
            ->assertSee('data-step-result-edit-open', false)
            ->assertSee('data-editor-state="disabled"', false)
            ->assertSee('data-step-result-toolbar', false)
            ->call('startEditingSelectedStepResult')
            ->assertSet('isEditingSelectedStepResult', true)
            ->assertSee('data-step-result-edit-save', false)
            ->assertSee('data-step-result-edit-cancel', false)
            ->assertSee('data-step-result-editor', false)
            ->assertSee('data-result-mode="edit"', false)
            ->assertSee('data-editor-state="enabled"', false)
            ->assertSee('data-step-result-toolbar', false)
            ->assertDontSee('wire:poll.1s="refreshSelectedStepResult"', false);
    }

    public function test_project_run_page_shows_restore_button_only_for_step_with_original_text(): void
    {
        [$project, $pipelineRun, $firstRunStep, $secondRunStep] = $this->createProjectRunWithSteps();

        $firstRunStep->update([
            'original' => "## Исходный текст\n\n- Пункт 1",
        ]);
        $secondRunStep->update([
            'original' => null,
        ]);

        Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun->fresh(),
        ])
            ->assertSee('data-step-result-restore-open', false)
            ->call('selectStep', $secondRunStep->id)
            ->assertDontSee('data-step-result-restore-open', false);
    }

    public function test_project_run_page_can_restore_selected_step_result_from_original_after_confirmation(): void
    {
        [$project, $pipelineRun, $firstRunStep] = $this->createProjectRunWithSteps();

        $originalMarkdown = "## Исходный текст\n\n- Исходный пункт";
        $firstRunStep->update([
            'original' => $originalMarkdown,
            'result' => "## Обновлённый текст\n\n- Изменённый пункт",
        ]);

        $component = Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun->fresh(),
        ]);
        $initialEditorRevision = $component->instance()->selectedStepEditorRevision;

        $component
            ->assertSet('isRestoreSelectedStepResultModalOpen', false)
            ->assertSee('data-step-result-restore-open', false)
            ->call('openRestoreSelectedStepResultModal')
            ->assertSet('isRestoreSelectedStepResultModalOpen', true)
            ->assertSee('data-step-result-restore-modal', false)
            ->assertSee('Восстановить изначальный текст шага?')
            ->call('restoreSelectedStepResult')
            ->assertSet('isRestoreSelectedStepResultModalOpen', false)
            ->assertSet('isEditingSelectedStepResult', false)
            ->assertDontSee('data-step-result-restore-modal', false);

        $firstRunStep->refresh();

        $this->assertSame($originalMarkdown, (string) $firstRunStep->result);
        $this->assertStringContainsString('<h2>Исходный текст</h2>', $component->instance()->selectedStepEditorHtml);
        $this->assertGreaterThan($initialEditorRevision, $component->instance()->selectedStepEditorRevision);

        $user = User::query()->firstOrFail();
        $expectedDescription = sprintf(
            '%s восстановил текст в шаге %d в уроке «%s» проекта «%s»',
            (string) $user->name,
            1,
            'Урок по Laravel',
            'Проект Ран',
        );

        $activity = Activity::query()
            ->where('log_name', 'pipeline-runs')
            ->where('event', 'updated')
            ->where('subject_type', PipelineRun::class)
            ->where('subject_id', $pipelineRun->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($expectedDescription, $activity->description);
        $this->assertSame('pipeline-run-step-result-restored', data_get($activity->properties, 'context'));
        $this->assertSame($firstRunStep->id, data_get($activity->properties, 'step_id'));
        $this->assertSame(1, data_get($activity->properties, 'step_number'));
    }

    public function test_project_run_page_can_save_selected_step_result_from_html_to_markdown_and_write_activity_log(): void
    {
        [$project, $pipelineRun, $firstRunStep] = $this->createProjectRunWithSteps();
        $initialResult = (string) $firstRunStep->result;

        $editedHtml = <<<'HTML'
<h1>Обновлённый заголовок</h1>
<div><strong>Жирный</strong> и <em>наклонный</em> текст</div>
<ul>
  <li>Пункт 1</li>
  <li>Пункт 2<ul><li>Вложенный</li></ul></li>
</ul>
<script>alert('xss')</script>
HTML;

        $expectedMarkdown = $this->convertHtmlToExpectedMarkdown($editedHtml);

        $component = Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun,
        ]);

        $component
            ->call('startEditingSelectedStepResult')
            ->set('selectedStepEditorHtml', $editedHtml)
            ->call('saveSelectedStepResult')
            ->assertSet('isEditingSelectedStepResult', false)
            ->assertSee('data-result-mode="preview"', false);

        $this->assertStringContainsString('<h1>Обновлённый заголовок</h1>', $component->instance()->selectedStepEditorHtml);
        $this->assertStringNotContainsString('Заголовок первого шага', $component->instance()->selectedStepEditorHtml);

        $firstRunStep->refresh();

        $this->assertSame($expectedMarkdown, (string) $firstRunStep->result);
        $this->assertSame($initialResult, (string) $firstRunStep->original);
        $this->assertStringNotContainsString('<script>', (string) $firstRunStep->result);

        $user = User::query()->firstOrFail();
        $expectedDescription = sprintf(
            '%s изменил текст в шаге %d в уроке «%s» проекта «%s»',
            (string) $user->name,
            1,
            'Урок по Laravel',
            'Проект Ран',
        );

        $activity = Activity::query()
            ->where('log_name', 'pipeline-runs')
            ->where('event', 'updated')
            ->where('subject_type', PipelineRun::class)
            ->where('subject_id', $pipelineRun->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($expectedDescription, $activity->description);
        $this->assertSame('pipeline-run-step-result-edited', data_get($activity->properties, 'context'));
        $this->assertSame($firstRunStep->id, data_get($activity->properties, 'step_id'));
        $this->assertSame(1, data_get($activity->properties, 'step_number'));
    }

    public function test_project_run_page_preserves_original_result_after_repeated_edits(): void
    {
        [$project, $pipelineRun, $firstRunStep] = $this->createProjectRunWithSteps();
        $initialResult = (string) $firstRunStep->result;

        $firstEditedHtml = '<h2>Первая правка</h2><div><strong>Текст</strong></div>';
        $secondEditedHtml = '<h3>Вторая правка</h3><ol><li>Первый</li><li>Второй</li></ol>';

        Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun,
        ])
            ->call('startEditingSelectedStepResult')
            ->set('selectedStepEditorHtml', $firstEditedHtml)
            ->call('saveSelectedStepResult')
            ->call('startEditingSelectedStepResult')
            ->set('selectedStepEditorHtml', $secondEditedHtml)
            ->call('saveSelectedStepResult');

        $firstRunStep->refresh();

        $this->assertSame($initialResult, (string) $firstRunStep->original);
        $this->assertSame(
            $this->convertHtmlToExpectedMarkdown($secondEditedHtml),
            (string) $firstRunStep->result
        );
    }

    public function test_project_run_page_does_not_merge_lists_with_previous_div_blocks_after_saving(): void
    {
        [$project, $pipelineRun, $firstRunStep] = $this->createProjectRunWithSteps();

        $editedHtml = <<<'HTML'
<h2>Проверка структуры</h2>
<div>Текст перед маркированным списком.</div>
<ul>
  <li>Пункт 1</li>
  <li>Пункт 2</li>
</ul>
<div><strong>Важные факты:</strong></div>
<ul>
  <li>Факт 1</li>
</ul>
<div>Темы для изучения:</div>
<ol>
  <li>Тема 1</li>
  <li>Тема 2</li>
</ol>
HTML;

        Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun,
        ])
            ->call('startEditingSelectedStepResult')
            ->set('selectedStepEditorHtml', $editedHtml)
            ->call('saveSelectedStepResult');

        $firstRunStep->refresh();
        $markdown = (string) $firstRunStep->result;

        $this->assertStringContainsString("Текст перед маркированным списком.\n\n- Пункт 1", $markdown);
        $this->assertStringContainsString("**Важные факты:**\n\n- Факт 1", $markdown);
        $this->assertStringContainsString("Темы для изучения:\n\n1. Тема 1", $markdown);

        $this->assertStringNotContainsString('Текст перед маркированным списком. - Пункт 1', $markdown);
        $this->assertStringNotContainsString('**Важные факты:**- Факт 1', $markdown);
        $this->assertStringNotContainsString('Темы для изучения:1. Тема 1', $markdown);
    }

    public function test_project_run_page_renders_markdown_when_result_contains_literal_newline_sequences(): void
    {
        [$project, $pipelineRun, $firstRunStep] = $this->createProjectRunWithSteps();

        $firstRunStep->update([
            'result' => '**Классификация Титхи:**\n1. **Амавасья:** Первый пункт\n2. **Цикл:** Второй пункт',
        ]);

        $component = Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun->fresh(),
        ]);

        $this->assertStringContainsString('<ol>', $component->instance()->selectedStepEditorHtml);
        $this->assertStringContainsString('<li><strong>Амавасья:</strong> Первый пункт</li>', $component->instance()->selectedStepEditorHtml);
        $this->assertStringContainsString('<li><strong>Цикл:</strong> Второй пункт</li>', $component->instance()->selectedStepEditorHtml);
    }

    public function test_project_run_page_keeps_nested_unordered_list_inside_ordered_item_when_markdown_is_valid(): void
    {
        [$project, $pipelineRun, $firstRunStep] = $this->createProjectRunWithSteps();

        $firstRunStep->update([
            'result' => <<<'MD'
**Классификация Титхи:**
1.  **Амавасья:** Момент, когда Луна и Солнце находятся в одной точке (соединение). Это самая темная ночь, когда Луны не видно.
2.  **Цикл:** Каждый раз, когда Луна отдаляется от Солнца на 12 градусов — наступает новый Титхи (новый вид отношений).
3.  **Количество:**
    *   15 Титхи на растущую Луну (Шукла Пакша).
    *   15 Титхи на убывающую Луну (Кришна Пакша).
    *   Итого существует **30 типов отношений** между Божественными Отцом и Матерью.
MD,
        ]);

        $component = Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun->fresh(),
        ]);

        $html = $component->instance()->selectedStepEditorHtml;

        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('<li><strong>Количество:</strong>', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringNotContainsString('</ol><ul>', str_replace(["\n", ' '], '', $html));
    }

    public function test_project_run_page_shows_failed_step_error_in_result_block_when_result_is_missing(): void
    {
        [$project, $pipelineRun, , $secondRunStep] = $this->createProjectRunWithSteps();

        $secondRunStep->update([
            'status' => 'failed',
            'result' => null,
            'error' => 'Ошибка LLM: timeout',
        ]);
        $pipelineRun->update(['status' => 'failed']);

        $component = Livewire::test(ProjectRunPage::class, [
            'project' => $project,
            'pipelineRun' => $pipelineRun->fresh(),
        ]);

        $component
            ->call('selectStep', $secondRunStep->id)
            ->assertSee('data-result-mode="preview"', false)
            ->assertSee('data-selected-step-error', false)
            ->assertSee('text-red-700', false)
            ->assertSee('Ошибка LLM: timeout')
            ->assertDontSee('Результат для выбранного шага пока не сформирован.')
            ->assertDontSee('data-result-mode="source"', false);

        $this->assertSame('Ошибка LLM: timeout', $component->instance()->selectedStepErrorMessage);
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

    private function convertHtmlToExpectedMarkdown(string $html): string
    {
        $sanitizedHtml = (string) Purifier::clean($html, 'default');
        $converter = app(StepResultHtmlToMarkdownConverter::class);

        return trim($converter->convert($sanitizedHtml));
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
