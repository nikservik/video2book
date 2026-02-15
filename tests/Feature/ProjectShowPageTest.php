<?php

namespace Tests\Feature;

use App\Jobs\DownloadLessonAudioJob;
use App\Jobs\ProcessPipelineJob;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('lg:col-span-2', false)
            ->assertSee('lg:col-span-1', false)
            ->assertSee('md:grid-cols-2', false)
            ->assertSee('Добавить урок')
            ->assertSee('Редактировать проект')
            ->assertSee('Скачать проект в PDF')
            ->assertSee('Скачать проект в MD')
            ->assertSee('Удалить проект')
            ->assertDontSee('data-create-lesson-modal', false)
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

        $response = $this->get(route('projects.show', $project));

        $response
            ->assertStatus(200)
            ->assertSee('md:grid-cols-2', false)
            ->assertSee('Урок с прогонами')
            ->assertSee('Базовый пайплайн • v4')
            ->assertSee('Готово')
            ->assertSee('В очереди')
            ->assertSee('Обработка')
            ->assertSee(route('projects.runs.show', ['project' => $project, 'pipelineRun' => $runDone]), false)
            ->assertSee(route('projects.runs.show', ['project' => $project, 'pipelineRun' => $runQueued]), false)
            ->assertSee(route('projects.runs.show', ['project' => $project, 'pipelineRun' => $runRunning]), false);
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
            ->call('openCreateLessonModal')
            ->assertDontSee('wire:poll.2s="refreshProjectLessons"', false);
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
            ->assertSee('data-audio-download-status="queued"', false)
            ->assertSee('data-audio-download-status="running"', false)
            ->assertSee('data-audio-download-status="loaded"', false)
            ->assertSee('text-red-500 dark:text-red-400', false)
            ->assertSee('text-gray-500 dark:text-gray-400', false)
            ->assertSee('text-yellow-500 dark:text-yellow-400', false)
            ->assertSee('text-green-500 dark:text-green-400', false);
    }

    public function test_delete_project_alert_can_be_opened_and_closed(): void
    {
        $project = Project::query()->create([
            'name' => 'Проект Сигма',
            'tags' => null,
        ]);

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->assertSet('showDeleteProjectAlert', false)
            ->call('openDeleteProjectAlert')
            ->assertSet('showDeleteProjectAlert', true)
            ->assertSee('Вы уверены, что хотите удалить проект?')
            ->assertSee('Удалить')
            ->assertSee('Отменить')
            ->call('closeDeleteProjectAlert')
            ->assertSet('showDeleteProjectAlert', false)
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

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->assertSet('showDeleteLessonAlert', false)
            ->call('openDeleteLessonAlert', $lesson->id)
            ->assertSet('showDeleteLessonAlert', true)
            ->assertSet('deletingLessonId', $lesson->id)
            ->assertSet('deletingLessonName', 'Урок для удаления')
            ->assertSee('Удалить урок «Урок для удаления»')
            ->assertSee('Вы уверены, что хотите удалить урок вместе со всеми расшифровками? Это действие нельзя отменить.')
            ->call('closeDeleteLessonAlert')
            ->assertSet('showDeleteLessonAlert', false)
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

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->assertSee('Удаляемый урок')
            ->assertSee('Оставшийся урок')
            ->call('openDeleteLessonAlert', $lessonToDelete->id)
            ->assertSet('showDeleteLessonAlert', true)
            ->call('deleteLesson')
            ->assertSet('showDeleteLessonAlert', false)
            ->assertDontSee('Удаляемый урок')
            ->assertSee('Оставшийся урок');

        $this->assertDatabaseMissing('lessons', ['id' => $lessonToDelete->id]);
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

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->assertSet('showDeleteRunAlert', false)
            ->call('openDeleteRunAlert', $run->id)
            ->assertSet('showDeleteRunAlert', true)
            ->assertSet('deletingRunId', $run->id)
            ->assertSet('deletingRunLabel', 'Пайплайн для удаления • v7')
            ->assertSee('Удалить прогон «Пайплайн для удаления • v7»')
            ->assertSee('Вы уверены, что хотите удалить прогон вместе со всеми расшифровками? Это действие нельзя отменить.')
            ->call('closeDeleteRunAlert')
            ->assertSet('showDeleteRunAlert', false)
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

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->assertSee('Пайплайн A • v1')
            ->assertSee('Пайплайн B • v2')
            ->call('openDeleteRunAlert', $runToDelete->id)
            ->assertSet('showDeleteRunAlert', true)
            ->call('deleteRun')
            ->assertSet('showDeleteRunAlert', false)
            ->assertDontSee('Пайплайн A • v1')
            ->assertSee('Пайплайн B • v2');

        $this->assertDatabaseMissing('pipeline_runs', ['id' => $runToDelete->id]);
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

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->assertSet('showRenameLessonModal', false)
            ->call('openRenameLessonModal', $lesson->id)
            ->assertSet('showRenameLessonModal', true)
            ->assertSet('editingLessonId', $lesson->id)
            ->assertSet('editableLessonName', 'Урок для переименования')
            ->assertSee('Изменить название урока')
            ->assertSee('Сохранить')
            ->assertSee('Отменить')
            ->call('closeRenameLessonModal')
            ->assertSet('showRenameLessonModal', false)
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

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->assertSee('Старое имя урока')
            ->call('openRenameLessonModal', $lesson->id)
            ->set('editableLessonName', 'Новое имя урока')
            ->call('saveLessonName')
            ->assertSet('showRenameLessonModal', false)
            ->assertDontSee('Старое имя урока')
            ->assertSee('Новое имя урока');

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

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->call('openRenameLessonModal', $lesson->id)
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

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->assertSet('showAddPipelineToLessonModal', false)
            ->call('openAddPipelineToLessonModal', $lesson->id)
            ->assertSet('showAddPipelineToLessonModal', true)
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

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->call('openAddPipelineToLessonModal', $lesson->id)
            ->set('addingPipelineVersionId', $newVersion->id)
            ->call('addPipelineToLesson')
            ->assertSet('showAddPipelineToLessonModal', false);

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

        Livewire::test(ProjectShowPage::class, ['project' => $project->fresh()])
            ->assertSet('showProjectExportModal', false)
            ->call('openProjectExportModal', 'pdf')
            ->assertSet('showProjectExportModal', true)
            ->assertSet('projectExportFormat', 'pdf')
            ->assertSet('projectExportSelection', $pipelineVersionB->id.':'.$stepVersionsB['text']->id)
            ->assertSee('Скачивание проекта в PDF')
            ->assertSee('Текстовый шаг A')
            ->assertSee('Текстовый шаг B')
            ->assertDontSee('Транскрибация')
            ->assertDontSee('Глоссарий');
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

        $expectedFilename = Str::slug($project->name.'-'.$stepVersions['text']->name.'-md', '_').'.zip';

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->call('openProjectExportModal', 'md')
            ->set('projectExportSelection', $pipelineVersion->id.':'.$stepVersions['text']->id)
            ->call('downloadProjectResults')
            ->assertSet('showProjectExportModal', false)
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

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->call('openDeleteProjectAlert')
            ->assertSet('showDeleteProjectAlert', true)
            ->call('deleteProject')
            ->assertRedirect(route('projects.index'));

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('lessons', ['project_id' => $project->id]);
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

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->assertSet('showRenameProjectModal', false)
            ->call('openRenameProjectModal')
            ->assertSet('showRenameProjectModal', true)
            ->assertSet('editableProjectName', 'Проект для переименования')
            ->assertSet('editableProjectReferer', 'https://www.example.com/')
            ->assertSet('editableProjectDefaultPipelineVersionId', $pipelineVersion->id)
            ->assertSee('Редактировать проект')
            ->assertSee('Referrer')
            ->assertSee('Версия пайплайна по умолчанию')
            ->assertSee('Сохранить')
            ->assertSee('Отменить')
            ->call('closeRenameProjectModal')
            ->assertSet('showRenameProjectModal', false)
            ->assertDontSee('Версия пайплайна по умолчанию');
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

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->call('openRenameProjectModal')
            ->set('editableProjectName', 'Новое название проекта')
            ->set('editableProjectReferer', 'https://www.somesite.com/')
            ->set('editableProjectDefaultPipelineVersionId', $pipelineVersion->id)
            ->call('saveProject')
            ->assertSet('project.name', 'Новое название проекта')
            ->assertSet('project.referer', 'https://www.somesite.com/')
            ->assertSet('project.default_pipeline_version_id', $pipelineVersion->id)
            ->assertSet('showRenameProjectModal', false);

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

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->call('openRenameProjectModal')
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

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->assertSet('showCreateLessonModal', false)
            ->call('openCreateLessonModal')
            ->assertSet('showCreateLessonModal', true)
            ->assertSet('newLessonPipelineVersionId', $pipelineVersion->id)
            ->assertSee('Добавить урок')
            ->assertSee('Ссылка на YouTube')
            ->assertSee('Версия пайплайна')
            ->call('closeCreateLessonModal')
            ->assertSet('showCreateLessonModal', false)
            ->assertDontSee('Ссылка на YouTube');
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

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->call('openCreateLessonModal')
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

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->call('openCreateLessonModal')
            ->set('newLessonName', 'Новый урок с YouTube')
            ->set('newLessonYoutubeUrl', 'https://www.youtube.com/watch?v=abc123')
            ->set('newLessonPipelineVersionId', $pipelineVersion->id)
            ->call('createLessonFromYoutube')
            ->assertSet('showCreateLessonModal', false)
            ->assertSee('Новый урок с YouTube');

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

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->call('openCreateLessonModal')
            ->set('newLessonName', '')
            ->set('newLessonYoutubeUrl', 'invalid-url')
            ->set('newLessonPipelineVersionId', $pipelineVersion->id)
            ->call('createLessonFromYoutube')
            ->assertHasErrors([
                'newLessonName' => 'required',
                'newLessonYoutubeUrl' => 'url',
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
