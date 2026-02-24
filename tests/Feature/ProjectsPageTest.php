<?php

namespace Tests\Feature;

use App\Actions\Project\RecalculateProjectLessonsAudioDurationAction;
use App\Jobs\DownloadLessonAudioJob;
use App\Livewire\ProjectsPage;
use App\Models\Folder;
use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Project;
use App\Models\ProjectTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_page_shows_projects_for_expanded_folder_sorted_by_last_update_with_lessons_count(): void
    {
        $this->ensureDefaultProjectTag();

        $folder = $this->defaultFolder();

        $this->createProject($folder, 'Старый проект', Carbon::parse('2026-01-10 10:00:00'), 1);
        $this->createProject($folder, 'Средний проект', Carbon::parse('2026-01-15 10:00:00'), 3);
        $this->createProject($folder, 'Новый проект', Carbon::parse('2026-01-20 10:00:00'), 0);

        $response = $this->get(route('projects.index'));

        $response
            ->assertStatus(200)
            ->assertSeeInOrder([
                'Новый проект',
                'Средний проект',
                'Старый проект',
            ])
            ->assertSee('Уроков: 3')
            ->assertSee('Уроков: 1')
            ->assertSee('Уроков: 0');
    }

    public function test_projects_page_shows_project_duration_from_settings(): void
    {
        $folder = $this->defaultFolder();

        Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Проект с длительностью',
            'tags' => null,
            'settings' => [
                RecalculateProjectLessonsAudioDurationAction::PROJECT_TOTAL_DURATION_SETTING_KEY => 3600,
            ],
        ]);

        $response = $this->get(route('projects.index'));

        $response
            ->assertStatus(200)
            ->assertSee('Длительность: 1ч 0м')
            ->assertSee('1ч 0м');
    }

    public function test_projects_page_rows_include_links_to_project_show_page(): void
    {
        $project = Project::query()->create([
            'name' => 'Проект со ссылкой',
            'tags' => null,
        ]);

        $response = $this->get(route('projects.index'));

        $response
            ->assertStatus(200)
            ->assertSee(route('projects.show', $project), false);
    }

    public function test_projects_page_create_folder_modal_can_be_opened_and_closed(): void
    {
        Livewire::test(ProjectsPage::class)
            ->assertSet('showCreateFolderModal', false)
            ->call('openCreateFolderModal')
            ->assertSet('showCreateFolderModal', true)
            ->assertSee('data-create-folder-modal', false)
            ->call('closeCreateFolderModal')
            ->assertSet('showCreateFolderModal', false)
            ->assertDontSee('data-create-folder-modal', false);
    }

    public function test_projects_page_can_create_and_rename_folder(): void
    {
        $component = Livewire::test(ProjectsPage::class)
            ->call('openCreateFolderModal')
            ->set('newFolderName', 'Курсы по теме')
            ->call('createFolder')
            ->assertSet('showCreateFolderModal', false)
            ->assertHasNoErrors();

        $folder = Folder::query()->where('name', 'Курсы по теме')->firstOrFail();

        $component
            ->call('openRenameFolderModal', $folder->id)
            ->set('editingFolderName', 'Курсы обновлённые')
            ->call('renameFolder')
            ->assertSet('showRenameFolderModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('folders', [
            'id' => $folder->id,
            'name' => 'Курсы обновлённые',
        ]);
    }

    public function test_projects_page_can_create_hidden_folder_with_locked_users(): void
    {
        $viewer = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_ADMIN,
        ]);
        $this->actingAs($viewer);

        $superAdmin = User::query()
            ->where('access_level', User::ACCESS_LEVEL_SUPERADMIN)
            ->firstOrFail();

        $visibleUser = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_USER,
            'name' => 'Видимый пользователь',
        ]);

        Livewire::test(ProjectsPage::class)
            ->call('openCreateFolderModal')
            ->set('newFolderName', 'Скрытая папка')
            ->set('newFolderHidden', true)
            ->set('newFolderVisibleFor', [$visibleUser->id])
            ->call('createFolder')
            ->assertSet('showCreateFolderModal', false)
            ->assertHasNoErrors();

        $folder = Folder::query()->where('name', 'Скрытая папка')->firstOrFail();

        $this->assertTrue((bool) $folder->hidden);
        $this->assertEqualsCanonicalizing(
            [$superAdmin->id, $viewer->id, $visibleUser->id],
            array_map(static fn (mixed $userId): int => (int) $userId, (array) $folder->visible_for)
        );
    }

    public function test_projects_page_edit_hidden_folder_keeps_locked_users_selected(): void
    {
        $viewer = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_ADMIN,
        ]);
        $this->actingAs($viewer);

        $superAdmin = User::query()
            ->where('access_level', User::ACCESS_LEVEL_SUPERADMIN)
            ->firstOrFail();

        $folder = Folder::query()->create([
            'name' => 'Закрытая папка',
            'hidden' => true,
            'visible_for' => [$viewer->id],
        ]);

        Livewire::test(ProjectsPage::class)
            ->call('openRenameFolderModal', $folder->id)
            ->set('editingFolderName', 'Закрытая папка 2')
            ->set('editingFolderHidden', true)
            ->set('editingFolderVisibleFor', [])
            ->call('renameFolder')
            ->assertSet('showRenameFolderModal', false)
            ->assertHasNoErrors();

        $folder->refresh();

        $this->assertTrue((bool) $folder->hidden);
        $this->assertEqualsCanonicalizing(
            [$superAdmin->id, $viewer->id],
            array_map(static fn (mixed $userId): int => (int) $userId, (array) $folder->visible_for)
        );
    }

    public function test_projects_page_create_project_modal_opens_for_selected_folder(): void
    {
        $folder = Folder::query()->create([
            'name' => 'Папка для создания',
        ]);

        Livewire::test(ProjectsPage::class)
            ->call('openCreateProjectModal', $folder->id)
            ->assertSet('showCreateProjectModal', true)
            ->assertSet('newProjectFolderId', $folder->id)
            ->assertSee('Добавить проект в «Папка для создания»');
    }

    public function test_projects_page_pipeline_dropdown_shows_pipeline_descriptions(): void
    {
        $versionWithDescription = $this->createPipelineWithSteps();
        $versionWithDescription->update([
            'description' => 'Пояснение для версии пайплайна',
        ]);

        $this->createPipelineWithSteps();

        Livewire::test(ProjectsPage::class)
            ->call('openCreateProjectModal')
            ->assertSee('Пояснение для версии пайплайна')
            ->assertSee('Описание не задано.');
    }

    public function test_projects_page_hides_pipeline_version_number_for_zero_access_level_user(): void
    {
        $this->createPipelineWithSteps();

        $user = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_USER,
        ]);

        $this->actingAs($user);

        Livewire::test(ProjectsPage::class)
            ->call('openCreateProjectModal')
            ->assertSee('Pipeline title')
            ->assertDontSee('Pipeline title • v1');
    }

    public function test_projects_page_shows_pipeline_version_number_for_admin(): void
    {
        $this->createPipelineWithSteps();

        Livewire::test(ProjectsPage::class)
            ->call('openCreateProjectModal')
            ->assertSee('Pipeline title • v1');
    }

    public function test_projects_page_can_create_project_with_only_required_name_in_selected_folder(): void
    {
        $folder = Folder::query()->create([
            'name' => 'Папка с проектами',
        ]);

        Livewire::test(ProjectsPage::class)
            ->call('openCreateProjectModal', $folder->id)
            ->set('newProjectName', 'Новый проект из модала')
            ->set('newProjectReferer', '')
            ->set('newProjectDefaultPipelineVersionId', null)
            ->set('newProjectLessonsList', '')
            ->call('createProject')
            ->assertSet('showCreateProjectModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('projects', [
            'folder_id' => $folder->id,
            'name' => 'Новый проект из модала',
            'referer' => null,
            'default_pipeline_version_id' => null,
        ]);

        $project = Project::query()->where('name', 'Новый проект из модала')->firstOrFail();
        $this->assertSame(0, $project->lessons()->count());
    }

    public function test_projects_page_can_create_project_and_lessons_from_list_in_selected_folder(): void
    {
        Queue::fake();

        $this->ensureDefaultProjectTag();

        $folder = Folder::query()->create([
            'name' => 'Папка с уроками',
        ]);

        $pipelineVersion = $this->createPipelineWithSteps();

        Livewire::test(ProjectsPage::class)
            ->call('openCreateProjectModal', $folder->id)
            ->set('newProjectName', 'Курс с уроками')
            ->set('newProjectReferer', 'https://www.somesite.com/')
            ->set('newProjectDefaultPipelineVersionId', $pipelineVersion->id)
            ->set('newProjectLessonsList', "Урок 1\nhttps://www.youtube.com/watch?v=video1\n\nУрок 2\nhttps://www.youtube.com/watch?v=video2")
            ->call('createProject')
            ->assertSet('showCreateProjectModal', false)
            ->assertHasNoErrors();

        $project = Project::query()->where('name', 'Курс с уроками')->firstOrFail();

        $this->assertSame($folder->id, $project->folder_id);
        $this->assertSame('https://www.somesite.com/', $project->referer);
        $this->assertSame($pipelineVersion->id, $project->default_pipeline_version_id);

        $lessons = $project->lessons()->orderBy('id')->pluck('name')->all();

        $this->assertSame(['Урок 1', 'Урок 2'], $lessons);

        Queue::assertPushedOn(DownloadLessonAudioJob::QUEUE, DownloadLessonAudioJob::class);
        Queue::assertPushed(DownloadLessonAudioJob::class, 2);
    }

    public function test_projects_page_requires_pipeline_version_when_lessons_list_is_filled(): void
    {
        Livewire::test(ProjectsPage::class)
            ->call('openCreateProjectModal')
            ->set('newProjectName', 'Проект с ошибкой')
            ->set('newProjectDefaultPipelineVersionId', null)
            ->set('newProjectLessonsList', "Урок\nhttps://www.youtube.com/watch?v=video")
            ->call('createProject')
            ->assertHasErrors(['newProjectDefaultPipelineVersionId']);

        $this->assertDatabaseMissing('projects', [
            'name' => 'Проект с ошибкой',
        ]);
    }

    public function test_projects_page_can_move_project_to_another_closed_folder(): void
    {
        $sourceFolder = Folder::query()->create([
            'name' => 'Исходная папка',
        ]);
        $targetFolder = Folder::query()->create([
            'name' => 'Целевая папка',
        ]);

        $project = Project::query()->create([
            'folder_id' => $sourceFolder->id,
            'name' => 'Проект для переноса',
            'tags' => null,
        ]);

        Livewire::test(ProjectsPage::class)
            ->set('expandedFolderId', $sourceFolder->id)
            ->call('moveProjectToFolder', $project->id, $targetFolder->id)
            ->assertSet('expandedFolderId', $targetFolder->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'folder_id' => $targetFolder->id,
        ]);
    }

    public function test_projects_page_hides_hidden_folders_for_user_without_access(): void
    {
        $viewer = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_USER,
            'access_token' => (string) Str::uuid(),
        ]);

        $visibleFolder = Folder::query()->create([
            'name' => 'Видимая папка',
            'hidden' => false,
            'visible_for' => [],
        ]);
        $hiddenFolder = Folder::query()->create([
            'name' => 'Скрытая папка',
            'hidden' => true,
            'visible_for' => [],
        ]);

        Project::query()->create([
            'folder_id' => $visibleFolder->id,
            'name' => 'Видимый проект',
            'tags' => null,
        ]);
        Project::query()->create([
            'folder_id' => $hiddenFolder->id,
            'name' => 'Скрытый проект',
            'tags' => null,
        ]);

        $response = $this
            ->withCookie((string) config('simple_auth.cookie_name'), (string) $viewer->access_token)
            ->get(route('projects.index'));

        $response
            ->assertStatus(200)
            ->assertSee('Видимая папка')
            ->assertDontSee('Видимый проект')
            ->assertDontSee('Скрытая папка')
            ->assertDontSee('Скрытый проект');
    }

    public function test_projects_page_keeps_all_folders_closed_by_default_when_more_than_one_visible_folder_exists(): void
    {
        $viewer = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_USER,
            'access_token' => (string) Str::uuid(),
        ]);

        $firstFolder = Folder::query()->create([
            'name' => 'Первая видимая папка',
            'hidden' => false,
            'visible_for' => [],
        ]);
        $secondFolder = Folder::query()->create([
            'name' => 'Вторая видимая папка',
            'hidden' => false,
            'visible_for' => [],
        ]);

        Project::query()->create([
            'folder_id' => $firstFolder->id,
            'name' => 'Проект первой папки',
            'tags' => null,
        ]);
        Project::query()->create([
            'folder_id' => $secondFolder->id,
            'name' => 'Проект второй папки',
            'tags' => null,
        ]);

        $response = $this
            ->withCookie((string) config('simple_auth.cookie_name'), (string) $viewer->access_token)
            ->get(route('projects.index'));

        $response
            ->assertStatus(200)
            ->assertSee('Первая видимая папка')
            ->assertSee('Вторая видимая папка')
            ->assertDontSee('Проект первой папки')
            ->assertDontSee('Проект второй папки');
    }

    public function test_projects_page_expands_folder_from_query_parameter_when_present(): void
    {
        $viewer = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_USER,
            'access_token' => (string) Str::uuid(),
        ]);

        $firstFolder = Folder::query()->create([
            'name' => 'Первая папка',
            'hidden' => false,
            'visible_for' => [],
        ]);
        $secondFolder = Folder::query()->create([
            'name' => 'Вторая папка',
            'hidden' => false,
            'visible_for' => [],
        ]);

        Project::query()->create([
            'folder_id' => $firstFolder->id,
            'name' => 'Проект первой папки',
            'tags' => null,
        ]);
        Project::query()->create([
            'folder_id' => $secondFolder->id,
            'name' => 'Проект второй папки',
            'tags' => null,
        ]);

        $response = $this
            ->withCookie((string) config('simple_auth.cookie_name'), (string) $viewer->access_token)
            ->get(route('projects.index', ['f' => $secondFolder->id]));

        $response
            ->assertStatus(200)
            ->assertSee('Первая папка')
            ->assertSee('Вторая папка')
            ->assertSee('Проект второй папки')
            ->assertDontSee('Проект первой папки');
    }

    private function createProject(Folder $folder, string $name, Carbon $updatedAt, int $lessonsCount): void
    {
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => $name,
            'tags' => null,
        ]);

        $project->timestamps = false;
        $project->forceFill([
            'created_at' => $updatedAt->copy()->subDay(),
            'updated_at' => $updatedAt,
        ])->saveQuietly();

        for ($index = 1; $index <= $lessonsCount; $index++) {
            Lesson::query()->create([
                'project_id' => $project->id,
                'name' => "{$name} Lesson {$index}",
                'tag' => 'default',
                'source_filename' => null,
                'settings' => [],
            ]);
        }
    }

    private function createPipelineWithSteps(): PipelineVersion
    {
        $pipeline = Pipeline::query()->create();
        $version = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Pipeline title',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);
        $pipeline->update(['current_version_id' => $version->id]);

        $step = $pipeline->steps()->create();
        $stepVersion = $step->versions()->create([
            'name' => 'Transcription',
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

        return $version;
    }

    private function defaultFolder(): Folder
    {
        return Folder::query()->where('name', 'Проекты')->firstOrFail();
    }

    private function ensureDefaultProjectTag(): void
    {
        ProjectTag::query()->firstOrCreate([
            'slug' => 'default',
        ], [
            'description' => null,
        ]);
    }
}
