<?php

namespace Tests\Feature;

use App\Jobs\DownloadLessonAudioJob;
use App\Jobs\ProcessPipelineJob;
use App\Livewire\ProjectShowPage;
use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Project;
use App\Models\ProjectTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
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
            ->assertSee('Изменить название')
            ->assertSee('Удалить проект')
            ->assertDontSee('data-create-lesson-modal', false)
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
        $project = Project::query()->create([
            'name' => 'Проект для переименования',
            'tags' => null,
        ]);

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->assertSet('showRenameProjectModal', false)
            ->call('openRenameProjectModal')
            ->assertSet('showRenameProjectModal', true)
            ->assertSet('editableProjectName', 'Проект для переименования')
            ->assertSee('Изменить название проекта')
            ->assertSee('Сохранить')
            ->assertSee('Отменить')
            ->call('closeRenameProjectModal')
            ->assertSet('showRenameProjectModal', false)
            ->assertDontSee('Изменить название проекта');
    }

    public function test_save_project_name_updates_project_and_closes_modal(): void
    {
        $project = Project::query()->create([
            'name' => 'Старое название',
            'tags' => null,
        ]);

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->call('openRenameProjectModal')
            ->set('editableProjectName', 'Новое название проекта')
            ->call('saveProjectName')
            ->assertSet('project.name', 'Новое название проекта')
            ->assertSet('showRenameProjectModal', false);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Новое название проекта',
        ]);
    }

    public function test_save_project_name_requires_non_empty_value(): void
    {
        $project = Project::query()->create([
            'name' => 'Название',
            'tags' => null,
        ]);

        Livewire::test(ProjectShowPage::class, ['project' => $project])
            ->call('openRenameProjectModal')
            ->set('editableProjectName', '')
            ->call('saveProjectName')
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
}
