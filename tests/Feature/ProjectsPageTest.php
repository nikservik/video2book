<?php

namespace Tests\Feature;

use App\Jobs\DownloadLessonAudioJob;
use App\Livewire\ProjectsPage;
use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Project;
use App\Models\ProjectTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_page_shows_projects_sorted_by_last_update_with_lessons_count(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $this->createProject('Старый проект', Carbon::parse('2026-01-10 10:00:00'), 1);
        $this->createProject('Средний проект', Carbon::parse('2026-01-15 10:00:00'), 3);
        $this->createProject('Новый проект', Carbon::parse('2026-01-20 10:00:00'), 0);

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

    public function test_projects_page_has_pagination(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        for ($index = 1; $index <= 16; $index++) {
            $name = sprintf('PRJ-%02d', $index);
            $updatedAt = Carbon::parse('2026-01-01 00:00:00')->addMinutes($index);
            $this->createProject($name, $updatedAt, 0);
        }

        $pageOne = $this->get(route('projects.index'));

        $pageOne
            ->assertStatus(200)
            ->assertSee('PRJ-16')
            ->assertSee('PRJ-02')
            ->assertDontSee('PRJ-01')
            ->assertSee('?page=2', false);

        $pageTwo = $this->get(route('projects.index', ['page' => 2]));

        $pageTwo
            ->assertStatus(200)
            ->assertSee('PRJ-01')
            ->assertDontSee('PRJ-16');
    }

    public function test_projects_page_cards_link_to_project_show_page(): void
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

    public function test_projects_page_create_project_modal_can_be_opened_and_closed(): void
    {
        Livewire::test(ProjectsPage::class)
            ->assertSet('showCreateProjectModal', false)
            ->call('openCreateProjectModal')
            ->assertSet('showCreateProjectModal', true)
            ->assertSee('data-create-project-modal', false)
            ->call('closeCreateProjectModal')
            ->assertSet('showCreateProjectModal', false)
            ->assertDontSee('data-create-project-modal', false);
    }

    public function test_projects_page_can_create_project_with_only_required_name(): void
    {
        Livewire::test(ProjectsPage::class)
            ->call('openCreateProjectModal')
            ->set('newProjectName', 'Новый проект из модала')
            ->set('newProjectReferer', '')
            ->set('newProjectDefaultPipelineVersionId', null)
            ->set('newProjectLessonsList', '')
            ->call('createProject')
            ->assertSet('showCreateProjectModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('projects', [
            'name' => 'Новый проект из модала',
            'referer' => null,
            'default_pipeline_version_id' => null,
        ]);

        $project = Project::query()->where('name', 'Новый проект из модала')->firstOrFail();
        $this->assertSame(0, $project->lessons()->count());
    }

    public function test_projects_page_can_create_project_and_lessons_from_list(): void
    {
        Queue::fake();

        $pipelineVersion = $this->createPipelineWithSteps();

        Livewire::test(ProjectsPage::class)
            ->call('openCreateProjectModal')
            ->set('newProjectName', 'Курс с уроками')
            ->set('newProjectReferer', 'https://www.somesite.com/')
            ->set('newProjectDefaultPipelineVersionId', $pipelineVersion->id)
            ->set('newProjectLessonsList', "Урок 1\nhttps://www.youtube.com/watch?v=video1\n\nУрок 2\nhttps://www.youtube.com/watch?v=video2")
            ->call('createProject')
            ->assertSet('showCreateProjectModal', false)
            ->assertHasNoErrors();

        $project = Project::query()->where('name', 'Курс с уроками')->firstOrFail();

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

    private function createProject(string $name, Carbon $updatedAt, int $lessonsCount): void
    {
        $project = Project::query()->create([
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
}
