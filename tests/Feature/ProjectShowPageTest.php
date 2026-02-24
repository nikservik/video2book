<?php

namespace Tests\Feature;

use App\Actions\Project\RecalculateProjectLessonsAudioDurationAction;
use App\Jobs\DownloadLessonAudioJob;
use App\Jobs\NormalizeUploadedLessonAudioJob;
use App\Jobs\ProcessPipelineJob;
use App\Livewire\ProjectShow\Modals\AddLessonFromAudioModal;
use App\Livewire\ProjectShow\Modals\AddLessonsListModal;
use App\Livewire\ProjectShow\Modals\AddPipelineToLessonModal;
use App\Livewire\ProjectShow\Modals\CreateLessonModal;
use App\Livewire\ProjectShow\Modals\DeleteLessonAlert;
use App\Livewire\ProjectShow\Modals\DeleteProjectAlert;
use App\Livewire\ProjectShow\Modals\DeleteRunAlert;
use App\Livewire\ProjectShow\Modals\ProjectExportModal;
use App\Livewire\ProjectShow\Modals\RenameLessonModal;
use App\Livewire\ProjectShow\Modals\RenameProjectModal;
use App\Livewire\ProjectShowPage;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectShowPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_page_shows_project_name_and_lessons_list(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект Омега',
            'tags' => null,
        ]);

        $this->createLesson($project->id, 'Урок 1', Carbon::parse('2026-02-01 10:00:00'));
        $this->createLesson($project->id, 'Урок 3', Carbon::parse('2026-02-03 10:00:00'));
        $this->createLesson($project->id, 'Урок 2', Carbon::parse('2026-02-02 10:00:00'));

        $response = $this->get(route('projects.show', $project));

        $response
            ->assertStatus(200)
            ->assertSee('Проект Омега')
            ->assertSeeInOrder(['Урок 1', 'Урок 2', 'Урок 3'])
            ->assertSee('Сортировка по дате добавления')
            ->assertSee('Сортировка по названию')
            ->assertSee('data-lesson-sort-select', false)
            ->assertSee('data-project-actions-toggle', false)
            ->assertSee('data-project-actions-menu', false)
            ->assertSeeInOrder([
                'data-project-actions-section="settings"',
                'data-project-actions-section="lessons"',
                'data-project-actions-section="exports"',
                'data-project-actions-section="danger"',
            ], false)
            ->assertSee('md:hidden', false)
            ->assertSee('md:block', false)
            ->assertSee('md:order-2', false)
            ->assertSee('md:grid-cols-3', false)
            ->assertSee('md:col-span-2', false)
            ->assertSee('md:grid-cols-2', false)
            ->assertSee('Добавить урок')
            ->assertSee('Добавить урок из аудио')
            ->assertSee('Редактировать проект')
            ->assertSee('Пересчитать длительность')
            ->assertSee('Скачать проект в PDF')
            ->assertSee('Скачать проект в MD')
            ->assertSee('Скачать проект в DOCX')
            ->assertSee('Удалить проект')
            ->assertDontSee('data-create-lesson-modal', false)
            ->assertDontSee('data-add-lesson-from-audio-modal', false)
            ->assertSee('data-add-lessons-list-button', false)
            ->assertSee('data-disabled="true"', false)
            ->assertDontSee('data-add-lessons-list-modal', false)
            ->assertDontSee('data-add-pipeline-to-lesson-modal', false)
            ->assertDontSee('data-project-export-modal', false)
            ->assertDontSee('data-rename-project-modal', false)
            ->assertDontSee('data-rename-lesson-modal', false)
            ->assertDontSee('data-delete-project-alert', false)
            ->assertDontSee('data-delete-lesson-alert', false)
            ->assertDontSee('data-delete-run-alert', false)
            ->assertDontSee('Уроков:');
    }

    public function test_project_page_shows_empty_state_when_project_has_no_lessons(): void
    {
        $project = Project::query()->create([
            'name' => 'Пустой проект',
            'tags' => null,
        ]);

        $response = $this->get(route('projects.show', $project));

        $response
            ->assertStatus(200)
            ->assertSee('Пустой проект')
            ->assertSee('В этом проекте пока нет уроков.');
    }

    public function test_project_page_shows_pipeline_runs_in_cards_with_badges_and_links(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект с прогонами',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок с прогонами',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        $pipeline = Pipeline::query()->create();
        $pipelineVersion = $pipeline->versions()->create([
            'version' => 4,
            'title' => 'Базовый пайплайн',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);

        $runDone = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'done',
            'state' => [],
        ]);
        $runQueued = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'queued',
            'state' => [],
        ]);
        $runRunning = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'running',
            'state' => [],
        ]);

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->assertSet('showPipelineRunVersionInLessonCard', true);

        $response = $this->get(route('projects.show', $project));

        $response
            ->assertStatus(200)
            ->assertSee('md:grid-cols-2', false)
            ->assertSee('Урок с прогонами')
            ->assertSee('Базовый пайплайн • v4')
            ->assertSee('aria-label="Удалить прогон"', false)
            ->assertSee('Готово')
            ->assertSee('В очереди')
            ->assertSee('Обработка')
            ->assertSee(route('projects.runs.show', ['project' => $project, 'pipelineRun' => $runDone]), false)
            ->assertSee(route('projects.runs.show', ['project' => $project, 'pipelineRun' => $runQueued]), false)
            ->assertSee(route('projects.runs.show', ['project' => $project, 'pipelineRun' => $runRunning]), false);
    }

    public function test_project_page_hides_pipeline_version_in_run_cards_for_zero_access_level_user(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект пользователя',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок пользователя',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        $pipeline = Pipeline::query()->create();
        $pipelineVersion = $pipeline->versions()->create([
            'version' => 5,
            'title' => 'Пайплайн пользователя',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);

        PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'done',
            'state' => [],
        ]);

        $user = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_USER,
            'access_token' => (string) Str::uuid(),
        ]);

        $response = $this
            ->withCookie((string) config('simple_auth.cookie_name'), (string) $user->access_token)
            ->get(route('projects.show', $project));

        $response
            ->assertStatus(200)
            ->assertSee('Пайплайн пользователя')
            ->assertDontSee('Пайплайн пользователя • v5')
            ->assertDontSee('aria-label="Удалить прогон"', false);
    }

    public function test_project_page_has_polling_for_lessons_list(): void
    {
        $project = Project::query()->create([
            'name' => 'Проект с поллингом',
            'tags' => null,
        ]);

        $this->get(route('projects.show', $project))
            ->assertStatus(200)
            ->assertSee('wire:poll.2s="refreshProjectLessons"', false);
    }

    public function test_project_page_disables_lessons_polling_while_modal_is_open(): void
    {
        $project = Project::query()->create([
            'name' => 'Проект с паузой поллинга',
            'tags' => null,
        ]);

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->assertSee('wire:poll.2s="refreshProjectLessons"', false)
            ->call('markModalOpened')
            ->assertDontSee('wire:poll.2s="refreshProjectLessons"', false);
    }

    public function test_project_page_disables_lessons_polling_while_audio_upload_is_in_progress(): void
    {
        $project = Project::query()->create([
            'name' => 'Проект с загрузкой аудио',
            'tags' => null,
        ]);

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->assertSee('wire:poll.2s="refreshProjectLessons"', false)
            ->dispatch('project-show:audio-upload-started')
            ->assertDontSee('wire:poll.2s="refreshProjectLessons"', false)
            ->dispatch('project-show:audio-upload-finished')
            ->assertSee('wire:poll.2s="refreshProjectLessons"', false);
    }

    public function test_project_page_disables_lessons_polling_while_lesson_sort_dropdown_is_open(): void
    {
        $project = Project::query()->create([
            'name' => 'Проект с открытой сортировкой',
            'tags' => null,
        ]);

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->assertSee('wire:poll.2s="refreshProjectLessons"', false)
            ->call('markLessonSortDropdownOpened')
            ->assertDontSee('wire:poll.2s="refreshProjectLessons"', false)
            ->call('markLessonSortDropdownClosed')
            ->assertSee('wire:poll.2s="refreshProjectLessons"', false);
    }

    public function test_project_page_can_recalculate_project_audio_duration(): void
    {
        $project = Project::query()->create([
            'name' => 'Проект для пересчёта длительности',
            'tags' => null,
            'settings' => [],
        ]);

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->call('recalculateProjectAudioDuration');

        $this->assertSame(
            0,
            data_get(
                $project->fresh()->settings,
                RecalculateProjectLessonsAudioDurationAction::PROJECT_TOTAL_DURATION_SETTING_KEY
            )
        );
    }

    public function test_project_page_keeps_lessons_order_when_lesson_sort_dropdown_state_changes(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект со стабильной сортировкой в dropdown',
            'tags' => null,
        ]);

        $this->createLesson($project->id, 'Урок 1', Carbon::parse('2026-02-01 10:00:00'));
        $this->createLesson($project->id, 'Урок 3', Carbon::parse('2026-02-03 10:00:00'));
        $this->createLesson($project->id, 'Урок 2', Carbon::parse('2026-02-02 10:00:00'));

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->assertSeeInOrder(['lesson-row-1', 'lesson-row-3', 'lesson-row-2'])
            ->call('markLessonSortDropdownOpened')
            ->assertSeeInOrder(['lesson-row-1', 'lesson-row-3', 'lesson-row-2'])
            ->call('markLessonSortDropdownClosed')
            ->assertSeeInOrder(['lesson-row-1', 'lesson-row-3', 'lesson-row-2']);
    }

    public function test_project_page_keeps_lessons_order_when_modal_state_changes(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект со стабильным порядком',
            'tags' => null,
        ]);

        $this->createLesson($project->id, 'Урок 1', Carbon::parse('2026-02-01 10:00:00'));
        $this->createLesson($project->id, 'Урок 3', Carbon::parse('2026-02-03 10:00:00'));
        $this->createLesson($project->id, 'Урок 2', Carbon::parse('2026-02-02 10:00:00'));

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->assertSeeInOrder(['lesson-row-1', 'lesson-row-3', 'lesson-row-2'])
            ->call('markModalOpened')
            ->assertSeeInOrder(['lesson-row-1', 'lesson-row-3', 'lesson-row-2'])
            ->call('markModalClosed')
            ->assertSeeInOrder(['lesson-row-1', 'lesson-row-3', 'lesson-row-2']);
    }

    public function test_project_page_sorts_lessons_by_name_when_saved_in_project_settings(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект с сортировкой по названию',
            'tags' => null,
            'settings' => [
                'lessons_sort' => 'name',
            ],
        ]);

        $this->createLesson($project->id, 'Gamma lesson', Carbon::parse('2026-02-01 10:00:00'));
        $this->createLesson($project->id, 'Alpha lesson', Carbon::parse('2026-02-02 10:00:00'));
        $this->createLesson($project->id, 'Beta lesson', Carbon::parse('2026-02-03 10:00:00'));

        $this->get(route('projects.show', $project))
            ->assertStatus(200)
            ->assertSeeInOrder(['Alpha lesson', 'Beta lesson', 'Gamma lesson']);
    }

    public function test_project_page_sorts_lessons_by_name_with_numeric_icu_collation_on_pgsql(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('ICU numeric collation sorting is only available on PostgreSQL.');
        }

        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект с numeric-сортировкой',
            'tags' => null,
            'settings' => [
                'lessons_sort' => 'name',
            ],
        ]);

        $this->createLesson($project->id, 'Lesson 10', Carbon::parse('2026-02-01 10:00:00'));
        $this->createLesson($project->id, 'Lesson 2', Carbon::parse('2026-02-02 10:00:00'));
        $this->createLesson($project->id, 'Lesson 1', Carbon::parse('2026-02-03 10:00:00'));

        $this->get(route('projects.show', $project))
            ->assertStatus(200)
            ->assertSeeInOrder(['Lesson 1', 'Lesson 2', 'Lesson 10']);
    }

    public function test_project_page_can_change_lessons_sort_and_persist_it_in_project_settings(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект для смены сортировки',
            'tags' => null,
        ]);

        $this->createLesson($project->id, 'Gamma lesson', Carbon::parse('2026-02-01 10:00:00'));
        $this->createLesson($project->id, 'Alpha lesson', Carbon::parse('2026-02-02 10:00:00'));
        $this->createLesson($project->id, 'Beta lesson', Carbon::parse('2026-02-03 10:00:00'));

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->assertSet('lessonSort', 'created_at')
            ->assertSeeInOrder(['lesson-row-1', 'lesson-row-2', 'lesson-row-3'])
            ->set('lessonSort', 'name')
            ->assertSet('lessonSort', 'name')
            ->assertSeeInOrder(['lesson-row-2', 'lesson-row-3', 'lesson-row-1']);

        $project->refresh();

        $this->assertSame('name', data_get($project->settings, 'lessons_sort'));
    }

    public function test_project_page_shows_audio_download_icon_states_for_lessons(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект со статусами загрузки',
            'tags' => null,
        ]);

        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Ошибка загрузки',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [
                'download_status' => 'failed',
                'download_error' => 'Network error',
            ],
        ]);

        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'В очереди загрузки',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [
                'download_status' => 'queued',
                'downloading' => true,
            ],
        ]);

        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Идет скачивание',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [
                'download_status' => 'running',
                'downloading' => true,
            ],
        ]);

        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Уже загружен',
            'tag' => 'default',
            'source_filename' => 'lessons/999.mp3',
            'settings' => [
                'download_status' => 'failed',
                'download_error' => 'Old error',
            ],
        ]);

        $response = $this->get(route('projects.show', $project));

        $response
            ->assertStatus(200)
            ->assertSee('data-audio-download-status="failed"', false)
            ->assertSee('data-audio-download-error="Network error"', false)
            ->assertDontSee('data-audio-download-error="Old error"', false)
            ->assertSee('data-audio-download-status="queued"', false)
            ->assertSee('data-audio-download-status="running"', false)
            ->assertSee('data-audio-download-status="loaded"', false)
            ->assertSee('text-red-500 dark:text-red-400', false)
            ->assertSee('text-gray-500 dark:text-gray-400', false)
            ->assertSee('text-yellow-500 dark:text-yellow-400', false)
            ->assertSee('text-green-500 dark:text-green-400', false);
    }

    public function test_project_page_shows_audio_duration_for_loaded_lessons_in_human_format(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект с длительностью',
            'tags' => null,
        ]);

        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок с длительностью',
            'tag' => 'default',
            'source_filename' => 'lessons/101.mp3',
            'settings' => [
                'audio_duration_seconds' => 5415,
            ],
        ]);

        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок в очереди',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [
                'download_status' => 'queued',
                'audio_duration_seconds' => 3600,
            ],
        ]);

        $this->get(route('projects.show', $project))
            ->assertStatus(200)
            ->assertSee('data-audio-duration="1ч 30м"', false)
            ->assertDontSee('data-audio-duration="1ч 0м"', false)
            ->assertSee('shrink-0 whitespace-nowrap', false);
    }

    public function test_project_page_shows_total_audio_duration_in_heading_when_calculated(): void
    {
        $project = Project::query()->create([
            'name' => 'Проект с общей длительностью',
            'tags' => null,
            'settings' => [
                RecalculateProjectLessonsAudioDurationAction::PROJECT_TOTAL_DURATION_SETTING_KEY => 5415,
            ],
        ]);

        $this->get(route('projects.show', $project))
            ->assertStatus(200)
            ->assertSee('Длительность 1ч 30м');
    }

    public function test_project_page_hides_total_audio_duration_in_heading_when_not_calculated(): void
    {
        $project = Project::query()->create([
            'name' => 'Проект без общей длительности',
            'tags' => null,
            'settings' => [],
        ]);

        $this->get(route('projects.show', $project))
            ->assertStatus(200)
            ->assertDontSee('Длительность ');
    }

    public function test_delete_project_alert_can_be_opened_and_closed(): void
    {
        $project = Project::query()->create([
            'name' => 'Проект Сигма',
            'tags' => null,
        ]);

        Livewire::test(DeleteProjectAlert::class, ['projectId' => $project->id])
            ->assertSet('show', false)
            ->call('open')
            ->assertSet('show', true)
            ->assertSee('Вы уверены, что хотите удалить проект?')
            ->assertSee('Удалить')
            ->assertSee('Отменить')
            ->call('close')
            ->assertSet('show', false)
            ->assertDontSee('Вы уверены, что хотите удалить проект?');
    }

    public function test_delete_lesson_alert_can_be_opened_and_closed(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект с уроком',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок для удаления',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        Livewire::test(DeleteLessonAlert::class, ['projectId' => $project->id])
            ->assertSet('show', false)
            ->call('open', $lesson->id)
            ->assertSet('show', true)
            ->assertSet('deletingLessonId', $lesson->id)
            ->assertSet('deletingLessonName', 'Урок для удаления')
            ->assertSee('Удалить урок «Урок для удаления»')
            ->assertSee('Вы уверены, что хотите удалить урок вместе со всеми расшифровками? Это действие нельзя отменить.')
            ->call('close')
            ->assertSet('show', false)
            ->assertSet('deletingLessonId', null)
            ->assertSet('deletingLessonName', '')
            ->assertDontSee('Удалить урок «Урок для удаления»');
    }

    public function test_delete_lesson_confirm_removes_lesson_and_refreshes_project_lessons(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект с двумя уроками',
            'tags' => null,
        ]);

        $lessonToDelete = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Удаляемый урок',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        $lessonToKeep = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Оставшийся урок',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        Livewire::test(DeleteLessonAlert::class, ['projectId' => $project->id])
            ->call('open', $lessonToDelete->id)
            ->assertSet('show', true)
            ->call('deleteLesson')
            ->assertSet('show', false);

        $this->assertSoftDeleted('lessons', ['id' => $lessonToDelete->id]);
        $this->assertDatabaseHas('lessons', ['id' => $lessonToKeep->id]);
    }

    public function test_delete_run_alert_can_be_opened_and_closed(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект с прогоном',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        $pipeline = Pipeline::query()->create();
        $version = $pipeline->versions()->create([
            'version' => 7,
            'title' => 'Пайплайн для удаления',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);

        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $version->id,
            'status' => 'done',
            'state' => [],
        ]);

        Livewire::test(DeleteRunAlert::class, ['projectId' => $project->id])
            ->assertSet('show', false)
            ->call('open', $run->id)
            ->assertSet('show', true)
            ->assertSet('deletingRunId', $run->id)
            ->assertSet('deletingRunLabel', 'Пайплайн для удаления • v7')
            ->assertSee('Удалить прогон «Пайплайн для удаления • v7»')
            ->assertSee('Вы уверены, что хотите удалить прогон вместе со всеми расшифровками? Это действие нельзя отменить.')
            ->call('close')
            ->assertSet('show', false)
            ->assertSet('deletingRunId', null)
            ->assertSet('deletingRunLabel', '')
            ->assertDontSee('Удалить прогон «Пайплайн для удаления • v7»');
    }

    public function test_delete_run_confirm_removes_run_and_refreshes_project_lessons(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект с прогонами',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        $pipelineA = Pipeline::query()->create();
        $versionA = $pipelineA->versions()->create([
            'version' => 1,
            'title' => 'Пайплайн A',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);

        $pipelineB = Pipeline::query()->create();
        $versionB = $pipelineB->versions()->create([
            'version' => 2,
            'title' => 'Пайплайн B',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);

        $runToDelete = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $versionA->id,
            'status' => 'done',
            'state' => [],
        ]);

        $runToKeep = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $versionB->id,
            'status' => 'queued',
            'state' => [],
        ]);

        Livewire::test(DeleteRunAlert::class, ['projectId' => $project->id])
            ->call('open', $runToDelete->id)
            ->assertSet('show', true)
            ->call('deleteRun')
            ->assertSet('show', false);

        $this->assertSoftDeleted('pipeline_runs', ['id' => $runToDelete->id]);
        $this->assertDatabaseHas('pipeline_runs', ['id' => $runToKeep->id]);
    }

    public function test_rename_lesson_modal_can_be_opened_and_closed(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект с уроком',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок для переименования',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        Livewire::test(RenameLessonModal::class, ['projectId' => $project->id])
            ->assertSet('show', false)
            ->call('open', $lesson->id)
            ->assertSet('show', true)
            ->assertSet('editingLessonId', $lesson->id)
            ->assertSet('editableLessonName', 'Урок для переименования')
            ->assertSee('Изменить название урока')
            ->assertSee('Сохранить')
            ->assertSee('Отменить')
            ->call('close')
            ->assertSet('show', false)
            ->assertSet('editingLessonId', null)
            ->assertSet('editableLessonName', '')
            ->assertDontSee('Изменить название урока');
    }

    public function test_save_lesson_name_updates_lesson_and_refreshes_project_lessons(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект с уроком',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Старое имя урока',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        Livewire::test(RenameLessonModal::class, ['projectId' => $project->id])
            ->call('open', $lesson->id)
            ->set('editableLessonName', 'Новое имя урока')
            ->call('saveLessonName')
            ->assertSet('show', false);

        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'name' => 'Новое имя урока',
        ]);
    }

    public function test_save_lesson_name_requires_non_empty_value(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект с уроком',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Имя урока',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        Livewire::test(RenameLessonModal::class, ['projectId' => $project->id])
            ->call('open', $lesson->id)
            ->set('editableLessonName', '')
            ->call('saveLessonName')
            ->assertHasErrors(['editableLessonName' => 'required']);
    }

    public function test_add_pipeline_to_lesson_modal_uses_project_default_and_rejects_existing_versions(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        [, $alreadyAddedVersion] = $this->createPipelineWithSteps();
        [, $defaultVersion] = $this->createPipelineWithSteps();

        $project = Project::query()->create([
            'name' => 'Проект с дефолтной версией',
            'tags' => null,
            'default_pipeline_version_id' => $defaultVersion->id,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок с прогоном',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $alreadyAddedVersion->id,
            'status' => 'done',
            'state' => [],
        ]);

        Livewire::test(AddPipelineToLessonModal::class, ['projectId' => $project->id])
            ->assertSet('show', false)
            ->call('open', $lesson->id)
            ->assertSet('show', true)
            ->assertSet('addingPipelineLessonId', $lesson->id)
            ->assertSet('addingPipelineLessonName', 'Урок с прогоном')
            ->assertSet('addingPipelineVersionId', $defaultVersion->id)
            ->set('addingPipelineVersionId', $alreadyAddedVersion->id)
            ->call('addPipelineToLesson')
            ->assertHasErrors(['addingPipelineVersionId' => 'in']);
    }

    public function test_add_pipeline_to_lesson_creates_pipeline_run_and_dispatches_job(): void
    {
        Queue::fake();

        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        [, $existingVersion] = $this->createPipelineWithSteps();
        [, $newVersion] = $this->createPipelineWithSteps();

        $project = Project::query()->create([
            'name' => 'Проект с уроком',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок для добавления версии',
            'tag' => 'default',
            'source_filename' => 'lessons/123.mp3',
            'settings' => [],
        ]);

        PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $existingVersion->id,
            'status' => 'done',
            'state' => [],
        ]);

        Livewire::test(AddPipelineToLessonModal::class, ['projectId' => $project->id])
            ->call('open', $lesson->id)
            ->set('addingPipelineVersionId', $newVersion->id)
            ->call('addPipelineToLesson')
            ->assertSet('show', false);

        $newRun = PipelineRun::query()
            ->where('lesson_id', $lesson->id)
            ->where('pipeline_version_id', $newVersion->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($newRun);

        Queue::assertPushedOn(
            ProcessPipelineJob::QUEUE,
            ProcessPipelineJob::class,
            fn (ProcessPipelineJob $job): bool => $job->pipelineRunId === $newRun->id
        );
    }

    public function test_refresh_project_lessons_updates_pipeline_run_statuses(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект со статусами',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок статусов',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        [, $pipelineVersion] = $this->createPipelineWithSteps();

        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'queued',
            'state' => [],
        ]);

        $component = Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->assertSee('В очереди');

        $run->update(['status' => 'running']);

        $component
            ->call('refreshProjectLessons')
            ->assertSee('Обработка');
    }

    public function test_project_export_modal_opens_with_text_steps_and_prefers_project_default_pipeline(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект для экспорта',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок для экспорта',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        [, $pipelineVersionA, $stepVersionsA] = $this->createPipelineWithCustomSteps([
            ['name' => 'Транскрибация', 'type' => 'transcribe'],
            ['name' => 'Текстовый шаг A', 'type' => 'text'],
            ['name' => 'Глоссарий', 'type' => 'glossary'],
        ]);
        [, $pipelineVersionB, $stepVersionsB] = $this->createPipelineWithCustomSteps([
            ['name' => 'Текстовый шаг B', 'type' => 'text'],
        ]);

        $project->update([
            'default_pipeline_version_id' => $pipelineVersionB->id,
        ]);

        $runA = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersionA->id,
            'status' => 'done',
            'state' => [],
        ]);
        $runB = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersionB->id,
            'status' => 'done',
            'state' => [],
        ]);

        PipelineRunStep::query()->create([
            'pipeline_run_id' => $runA->id,
            'step_version_id' => $stepVersionsA['transcribe']->id,
            'position' => 1,
            'status' => 'done',
            'result' => 'transcribe',
        ]);
        PipelineRunStep::query()->create([
            'pipeline_run_id' => $runA->id,
            'step_version_id' => $stepVersionsA['text']->id,
            'position' => 2,
            'status' => 'done',
            'result' => 'text A',
        ]);
        PipelineRunStep::query()->create([
            'pipeline_run_id' => $runA->id,
            'step_version_id' => $stepVersionsA['glossary']->id,
            'position' => 3,
            'status' => 'done',
            'result' => 'glossary',
        ]);
        PipelineRunStep::query()->create([
            'pipeline_run_id' => $runB->id,
            'step_version_id' => $stepVersionsB['text']->id,
            'position' => 1,
            'status' => 'done',
            'result' => 'text B',
        ]);

        Livewire::test(ProjectExportModal::class, ['projectId' => $project->fresh()->id])
            ->assertSet('show', false)
            ->call('open', 'pdf')
            ->assertSet('show', true)
            ->assertSet('projectExportFormat', 'pdf')
            ->assertSet('projectExportArchiveFileNaming', 'lesson_step')
            ->assertSet('projectExportSelection', $pipelineVersionB->id.':'.$stepVersionsB['text']->id)
            ->assertSee('Скачивание проекта в PDF')
            ->assertSee('Именование файлов в архиве')
            ->assertSee('Урок.pdf')
            ->assertSee('Урок - шаг.pdf')
            ->assertSee('Текстовый шаг A')
            ->assertSee('Текстовый шаг B')
            ->assertDontSee('Транскрибация')
            ->assertDontSee('Глоссарий')
            ->call('open', 'docx')
            ->assertSet('projectExportFormat', 'docx')
            ->assertSee('Урок.docx')
            ->assertSee('Урок - шаг.docx')
            ->assertSee('Скачивание проекта в DOCX');
    }

    public function test_project_export_download_creates_zip_for_selected_step_and_skips_unprocessed_lessons(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект Архив',
            'tags' => null,
        ]);

        $lessonWithResult = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок 1',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        $lessonWithoutResult = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок 2',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        [, $pipelineVersion, $stepVersions] = $this->createPipelineWithCustomSteps([
            ['name' => 'Текстовый экспорт', 'type' => 'text'],
        ]);

        $runWithResult = PipelineRun::query()->create([
            'lesson_id' => $lessonWithResult->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'done',
            'state' => [],
        ]);
        $runWithoutResult = PipelineRun::query()->create([
            'lesson_id' => $lessonWithoutResult->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'done',
            'state' => [],
        ]);

        PipelineRunStep::query()->create([
            'pipeline_run_id' => $runWithResult->id,
            'step_version_id' => $stepVersions['text']->id,
            'position' => 1,
            'status' => 'done',
            'result' => '# Результат урока 1',
        ]);
        PipelineRunStep::query()->create([
            'pipeline_run_id' => $runWithoutResult->id,
            'step_version_id' => $stepVersions['text']->id,
            'position' => 1,
            'status' => 'pending',
            'result' => null,
        ]);

        $expectedFilename = Str::slug($project->name, '_').'.zip';

        Livewire::test(ProjectExportModal::class, ['projectId' => $project->id])
            ->call('open', 'md')
            ->set('projectExportSelection', $pipelineVersion->id.':'.$stepVersions['text']->id)
            ->call('downloadProjectResults')
            ->assertSet('show', false)
            ->assertFileDownloaded($expectedFilename, contentType: 'application/zip');
    }

    public function test_project_export_download_creates_docx_zip_for_selected_step(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект Архив DOCX',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок 1',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        [, $pipelineVersion, $stepVersions] = $this->createPipelineWithCustomSteps([
            ['name' => 'Текстовый экспорт', 'type' => 'text'],
        ]);

        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'done',
            'state' => [],
        ]);

        PipelineRunStep::query()->create([
            'pipeline_run_id' => $run->id,
            'step_version_id' => $stepVersions['text']->id,
            'position' => 1,
            'status' => 'done',
            'result' => "# Результат урока 1\n\n- **Пункт**",
        ]);

        $expectedFilename = Str::slug($project->name, '_').'.zip';

        Livewire::test(ProjectExportModal::class, ['projectId' => $project->id])
            ->call('open', 'docx')
            ->set('projectExportSelection', $pipelineVersion->id.':'.$stepVersions['text']->id)
            ->call('downloadProjectResults')
            ->assertSet('show', false)
            ->assertFileDownloaded($expectedFilename, contentType: 'application/zip');
    }

    public function test_delete_project_confirm_uses_action_and_removes_project(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект к удалению',
            'tags' => null,
        ]);

        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок для удаления',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        Livewire::test(DeleteProjectAlert::class, ['projectId' => $project->id])
            ->call('open')
            ->assertSet('show', true)
            ->call('deleteProject')
            ->assertRedirect(route('projects.index'));

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
        $this->assertDatabaseHas('lessons', ['project_id' => $project->id]);
    }

    public function test_rename_project_modal_can_be_opened_and_closed(): void
    {
        [, $pipelineVersion] = $this->createPipelineWithSteps();

        $project = Project::query()->create([
            'name' => 'Проект для переименования',
            'tags' => null,
            'referer' => 'https://www.example.com/',
            'default_pipeline_version_id' => $pipelineVersion->id,
        ]);

        Livewire::test(RenameProjectModal::class, ['projectId' => $project->id])
            ->assertSet('show', false)
            ->call('open')
            ->assertSet('show', true)
            ->assertSet('editableProjectName', 'Проект для переименования')
            ->assertSet('editableProjectReferer', 'https://www.example.com/')
            ->assertSet('editableProjectDefaultPipelineVersionId', $pipelineVersion->id)
            ->assertSee('Редактировать проект')
            ->assertSee('Referrer')
            ->assertSee('Версия шаблона по умолчанию')
            ->assertSee('Сохранить')
            ->assertSee('Отменить')
            ->call('close')
            ->assertSet('show', false)
            ->assertDontSee('Версия шаблона по умолчанию');
    }

    public function test_save_project_updates_project_and_closes_modal(): void
    {
        [, $pipelineVersion] = $this->createPipelineWithSteps();

        $project = Project::query()->create([
            'name' => 'Старое название',
            'tags' => null,
            'referer' => null,
            'default_pipeline_version_id' => null,
        ]);

        Livewire::test(RenameProjectModal::class, ['projectId' => $project->id])
            ->call('open')
            ->set('editableProjectName', 'Новое название проекта')
            ->set('editableProjectReferer', 'https://www.somesite.com/')
            ->set('editableProjectDefaultPipelineVersionId', $pipelineVersion->id)
            ->call('saveProject')
            ->assertSet('show', false);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Новое название проекта',
            'referer' => 'https://www.somesite.com/',
            'default_pipeline_version_id' => $pipelineVersion->id,
        ]);
    }

    public function test_save_project_requires_non_empty_name(): void
    {
        $project = Project::query()->create([
            'name' => 'Название',
            'tags' => null,
        ]);

        Livewire::test(RenameProjectModal::class, ['projectId' => $project->id])
            ->call('open')
            ->set('editableProjectName', '')
            ->call('saveProject')
            ->assertHasErrors(['editableProjectName' => 'required']);
    }

    public function test_create_lesson_modal_can_be_opened_and_closed(): void
    {
        $project = Project::query()->create([
            'name' => 'Проект для урока',
            'tags' => null,
        ]);

        [, $pipelineVersion] = $this->createPipelineWithSteps();

        Livewire::test(CreateLessonModal::class, ['projectId' => $project->id])
            ->assertSet('show', false)
            ->call('open')
            ->assertSet('show', true)
            ->assertSet('newLessonPipelineVersionId', $pipelineVersion->id)
            ->assertSee('Добавить урок')
            ->assertSee('Ссылка на YouTube')
            ->assertSee('Версия шаблона')
            ->call('close')
            ->assertSet('show', false)
            ->assertDontSee('Ссылка на YouTube');
    }

    public function test_add_lesson_from_audio_modal_can_be_opened_and_closed(): void
    {
        $project = Project::query()->create([
            'name' => 'Проект для аудио-урока',
            'tags' => null,
        ]);

        [, $pipelineVersion] = $this->createPipelineWithSteps();

        Livewire::test(AddLessonFromAudioModal::class, ['projectId' => $project->id])
            ->assertSet('show', false)
            ->call('open')
            ->assertSet('show', true)
            ->assertSet('newLessonPipelineVersionId', $pipelineVersion->id)
            ->assertSee('Добавить урок из аудио')
            ->assertSee('Перетащите аудиофайл сюда или нажмите для выбора')
            ->assertSee('Не удалось загрузить файл')
            ->assertSee('Обновить страницу')
            ->assertSee('Версия шаблона')
            ->call('close')
            ->assertSet('show', false)
            ->assertDontSee('Перетащите аудиофайл сюда или нажмите для выбора');
    }

    public function test_add_lessons_list_modal_can_be_opened_and_closed(): void
    {
        [, $pipelineVersion] = $this->createPipelineWithSteps();

        $project = Project::query()->create([
            'name' => 'Проект для списка уроков',
            'tags' => null,
            'default_pipeline_version_id' => $pipelineVersion->id,
        ]);

        Livewire::test(AddLessonsListModal::class, ['projectId' => $project->id])
            ->assertSet('show', false)
            ->call('open')
            ->assertSet('show', true)
            ->assertSee('Добавить список уроков')
            ->assertSee('Список уроков')
            ->call('close')
            ->assertSet('show', false)
            ->assertDontSee('Список уроков');
    }

    public function test_add_lessons_list_modal_creates_lessons_and_queues_download_jobs(): void
    {
        Queue::fake();

        [, $pipelineVersion] = $this->createPipelineWithSteps();

        $project = Project::query()->create([
            'name' => 'Проект для массового добавления',
            'tags' => null,
            'default_pipeline_version_id' => $pipelineVersion->id,
        ]);

        Livewire::test(AddLessonsListModal::class, ['projectId' => $project->id])
            ->call('open')
            ->set('newLessonsList', "Урок 1\nhttps://www.youtube.com/watch?v=video1\n\nУрок 2\nhttps://www.youtube.com/watch?v=video2")
            ->call('addLessons')
            ->assertSet('show', false)
            ->assertHasNoErrors();

        $lessons = $project->lessons()->orderBy('id')->pluck('name')->all();

        $this->assertSame(['Урок 1', 'Урок 2'], $lessons);

        Queue::assertPushedOn(DownloadLessonAudioJob::QUEUE, DownloadLessonAudioJob::class);
        Queue::assertPushed(DownloadLessonAudioJob::class, 2);
    }

    public function test_add_lessons_list_modal_requires_default_pipeline_on_project(): void
    {
        $project = Project::query()->create([
            'name' => 'Проект без пайплайна по умолчанию',
            'tags' => null,
            'default_pipeline_version_id' => null,
        ]);

        Livewire::test(AddLessonsListModal::class, ['projectId' => $project->id])
            ->call('open')
            ->set('newLessonsList', "Урок\nhttps://www.youtube.com/watch?v=video")
            ->call('addLessons')
            ->assertHasErrors(['newLessonsList']);

        $this->assertSame(0, $project->lessons()->count());
    }

    public function test_create_lesson_modal_selects_project_default_pipeline_version_when_available(): void
    {
        [, $firstPipelineVersion] = $this->createPipelineWithSteps();
        [, $defaultPipelineVersion] = $this->createPipelineWithSteps();

        $project = Project::query()->create([
            'name' => 'Проект с дефолтным пайплайном',
            'tags' => null,
            'default_pipeline_version_id' => $defaultPipelineVersion->id,
        ]);

        Livewire::test(CreateLessonModal::class, ['projectId' => $project->id])
            ->call('open')
            ->assertSet('newLessonPipelineVersionId', $defaultPipelineVersion->id)
            ->assertNotSet('newLessonPipelineVersionId', $firstPipelineVersion->id);
    }

    public function test_create_lesson_from_youtube_creates_lesson_and_queues_download_before_pipeline_processing(): void
    {
        Queue::fake();

        $project = Project::query()->create([
            'name' => 'Проект с новым уроком',
            'tags' => null,
        ]);
        [, $pipelineVersion] = $this->createPipelineWithSteps();

        Livewire::test(CreateLessonModal::class, ['projectId' => $project->id])
            ->call('open')
            ->set('newLessonName', 'Новый урок с YouTube')
            ->set('newLessonYoutubeUrl', 'https://www.youtube.com/watch?v=abc123')
            ->set('newLessonPipelineVersionId', $pipelineVersion->id)
            ->call('createLessonFromYoutube')
            ->assertSet('show', false);

        $lesson = Lesson::query()
            ->where('project_id', $project->id)
            ->where('name', 'Новый урок с YouTube')
            ->first();

        $this->assertNotNull($lesson);
        $this->assertSame('queued', data_get($lesson?->settings, 'download_status'));
        $this->assertTrue((bool) data_get($lesson?->settings, 'downloading'));

        $this->assertDatabaseHas('pipeline_runs', [
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'queued',
        ]);

        Queue::assertPushedOn(DownloadLessonAudioJob::QUEUE, DownloadLessonAudioJob::class, function (DownloadLessonAudioJob $job) use ($lesson): bool {
            return $job->lessonId === $lesson->id
                && $job->sourceUrl === 'https://www.youtube.com/watch?v=abc123';
        });

        Queue::assertNotPushed(ProcessPipelineJob::class);
    }

    public function test_create_lesson_from_youtube_requires_valid_fields(): void
    {
        $project = Project::query()->create([
            'name' => 'Проект с валидацией',
            'tags' => null,
        ]);
        [, $pipelineVersion] = $this->createPipelineWithSteps();

        Livewire::test(CreateLessonModal::class, ['projectId' => $project->id])
            ->call('open')
            ->set('newLessonName', '')
            ->set('newLessonYoutubeUrl', 'invalid-url')
            ->set('newLessonPipelineVersionId', $pipelineVersion->id)
            ->call('createLessonFromYoutube')
            ->assertHasErrors([
                'newLessonName' => 'required',
                'newLessonYoutubeUrl' => 'url',
            ]);
    }

    public function test_create_lesson_from_audio_queues_normalization_before_pipeline_processing(): void
    {
        Queue::fake();

        $project = Project::query()->create([
            'name' => 'Проект с аудио-уроком',
            'tags' => null,
        ]);
        [, $pipelineVersion] = $this->createPipelineWithSteps();

        Livewire::test(AddLessonFromAudioModal::class, ['projectId' => $project->id])
            ->call('open')
            ->set('newLessonName', 'Новый урок из файла')
            ->set('newLessonAudioFile', UploadedFile::fake()->create('lesson.wav', 512, 'audio/wav'))
            ->set('newLessonPipelineVersionId', $pipelineVersion->id)
            ->call('createLessonFromAudio')
            ->assertSet('show', false);

        $lesson = Lesson::query()
            ->where('project_id', $project->id)
            ->where('name', 'Новый урок из файла')
            ->first();

        $this->assertNotNull($lesson);
        $this->assertSame('queued', data_get($lesson?->settings, 'download_status'));
        $this->assertTrue((bool) data_get($lesson?->settings, 'downloading'));
        $this->assertSame('uploaded_audio', data_get($lesson?->settings, 'download_source'));

        $this->assertDatabaseHas('pipeline_runs', [
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'queued',
        ]);

        Queue::assertPushedOn(NormalizeUploadedLessonAudioJob::QUEUE, NormalizeUploadedLessonAudioJob::class, function (NormalizeUploadedLessonAudioJob $job) use ($lesson): bool {
            return $job->lessonId === $lesson->id
                && str_starts_with($job->uploadedAudioPath, 'downloader/'.$lesson->id.'/');
        });

        Queue::assertNotPushed(ProcessPipelineJob::class);
    }

    public function test_create_lesson_from_audio_requires_valid_fields(): void
    {
        $project = Project::query()->create([
            'name' => 'Проект с аудио-валидацией',
            'tags' => null,
        ]);
        [, $pipelineVersion] = $this->createPipelineWithSteps();

        Livewire::test(AddLessonFromAudioModal::class, ['projectId' => $project->id])
            ->call('open')
            ->set('newLessonName', '')
            ->set('newLessonAudioFile', UploadedFile::fake()->create('notes.txt', 10, 'text/plain'))
            ->set('newLessonPipelineVersionId', $pipelineVersion->id)
            ->call('createLessonFromAudio')
            ->assertHasErrors([
                'newLessonName' => 'required',
                'newLessonAudioFile' => 'mimetypes',
            ]);
    }

    private function createLesson(int $projectId, string $name, Carbon $updatedAt): void
    {
        $lesson = Lesson::query()->create([
            'project_id' => $projectId,
            'name' => $name,
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        $lesson->timestamps = false;
        $lesson->forceFill([
            'created_at' => $updatedAt->copy()->subDay(),
            'updated_at' => $updatedAt,
        ])->saveQuietly();
    }

    /**
     * @return array{0: Pipeline, 1: PipelineVersion}
     */
    private function createPipelineWithSteps(): array
    {
        $pipeline = Pipeline::query()->create();
        $version = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Пайплайн для уроков',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);
        $pipeline->update(['current_version_id' => $version->id]);

        $step = $pipeline->steps()->create();
        $stepVersion = $step->versions()->create([
            'name' => 'Транскрибация',
            'type' => 'transcribe',
            'version' => 1,
            'description' => null,
            'prompt' => 'Transcribe audio',
            'settings' => [
                'provider' => 'openai',
                'model' => 'whisper-1',
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

        return [$pipeline, $version];
    }

    /**
     * @param  array<int, array{name:string,type:string}>  $steps
     * @return array{0: Pipeline, 1: PipelineVersion, 2: array<string, StepVersion>}
     */
    private function createPipelineWithCustomSteps(array $steps): array
    {
        $pipeline = Pipeline::query()->create();
        $version = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Пайплайн с шагами',
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

            $stepVersions[$stepData['type']] = $stepVersion;
        }

        return [$pipeline, $version, $stepVersions];
    }
}
