<?php

namespace Tests\Feature;

use App\Actions\Pipeline\GetPipelineVersionOptionsAction;
use App\Jobs\NormalizeUploadedLessonAudioJob;
use App\Mcp\Support\McpPresenter;
use App\Models\Folder;
use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Project;
use App\Models\ProjectTag;
use App\Models\Step;
use App\Models\StepVersion;
use App\Models\User;
use App\Services\Project\ProjectDetailsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectLessonsApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_project_lessons_endpoint_returns_lessons_for_visible_project(): void
    {
        $viewer = $this->makeUser();
        [$project] = $this->createProjectWithLessonAndRun();

        $expectedProject = app(ProjectDetailsQuery::class)
            ->get($project)
            ->loadCount('lessons');

        $this->withToken((string) $viewer->access_token)
            ->getJson("/api/projects/{$project->id}/lessons")
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'project' => app(McpPresenter::class)->project($expectedProject),
                    'pipeline_versions' => app(GetPipelineVersionOptionsAction::class)->handle($viewer),
                    'lessons' => $expectedProject->lessons
                        ->map(fn (Lesson $lesson): array => app(McpPresenter::class)->lesson($lesson))
                        ->values()
                        ->all(),
                ],
            ])
            ->assertJsonPath('data.lessons.0.source_url', 'https://www.youtube.com/watch?v=abc123');
    }

    public function test_project_lessons_endpoint_returns_not_found_for_hidden_project(): void
    {
        $viewer = $this->makeUser(User::ACCESS_LEVEL_USER);
        $folder = Folder::query()->create([
            'name' => 'Скрытая папка',
            'hidden' => true,
            'visible_for' => [],
        ]);
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Скрытый проект',
            'tags' => null,
        ]);

        $this->withToken((string) $viewer->access_token)
            ->getJson("/api/projects/{$project->id}/lessons")
            ->assertNotFound();
    }

    public function test_store_creates_lesson_from_uploaded_audio_using_project_default_pipeline_version(): void
    {
        Queue::fake();

        $viewer = $this->makeUser();
        $folder = $this->createFolder();
        $pipelineVersion = $this->createPipelineVersionWithTextStep()[1];
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Project',
            'tags' => null,
            'default_pipeline_version_id' => $pipelineVersion->id,
        ]);

        $response = $this->withToken((string) $viewer->access_token)
            ->post("/api/projects/{$project->id}/lessons", [
                'name' => 'Audio Lesson',
                'source_url' => 'https://www.youtube.com/watch?v=uploaded-audio-source',
                'file' => UploadedFile::fake()->create('lesson.wav', 512, 'audio/wav'),
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonPath('data.project.lessons_count', 1)
            ->assertJsonPath('data.lesson.name', 'Audio Lesson')
            ->assertJsonPath('data.lesson.source_url', 'https://www.youtube.com/watch?v=uploaded-audio-source')
            ->assertJsonPath('data.lesson.download_status', 'running')
            ->assertJsonPath('data.lesson.runs.0.pipeline_version_id', $pipelineVersion->id);

        $lesson = Lesson::query()->firstWhere('name', 'Audio Lesson');

        $this->assertNotNull($lesson);
        $this->assertSame('https://www.youtube.com/watch?v=uploaded-audio-source', data_get($lesson->settings, 'url'));
        $this->assertDatabaseHas('pipeline_runs', [
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'queued',
        ]);
        Queue::assertPushedOn(
            NormalizeUploadedLessonAudioJob::QUEUE,
            NormalizeUploadedLessonAudioJob::class
        );
    }

    public function test_store_validates_source_url_when_it_is_present(): void
    {
        Queue::fake();

        $viewer = $this->makeUser();
        $folder = $this->createFolder();
        $pipelineVersion = $this->createPipelineVersionWithTextStep()[1];
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Project',
            'tags' => null,
            'default_pipeline_version_id' => $pipelineVersion->id,
        ]);

        $this->withToken((string) $viewer->access_token)
            ->post("/api/projects/{$project->id}/lessons", [
                'name' => 'Audio Lesson',
                'source_url' => 'not-a-valid-url',
                'file' => UploadedFile::fake()->create('lesson.wav', 512, 'audio/wav'),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['source_url']);
    }

    public function test_store_requires_pipeline_version_when_project_default_is_missing(): void
    {
        Queue::fake();

        $viewer = $this->makeUser();
        $folder = $this->createFolder();
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Project without default pipeline',
            'tags' => null,
            'default_pipeline_version_id' => null,
        ]);

        $this->withToken((string) $viewer->access_token)
            ->post("/api/projects/{$project->id}/lessons", [
                'name' => 'Audio Lesson',
                'file' => UploadedFile::fake()->create('lesson.wav', 512, 'audio/wav'),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pipeline_version_id']);
    }

    private function makeUser(int $accessLevel = User::ACCESS_LEVEL_ADMIN): User
    {
        return User::factory()->create([
            'access_token' => (string) Str::uuid(),
            'access_level' => $accessLevel,
        ]);
    }

    private function createFolder(): Folder
    {
        return Folder::query()->create([
            'name' => 'Folder '.Str::random(6),
            'hidden' => false,
            'visible_for' => [],
        ]);
    }

    /**
     * @return array{0: Project, 1: Lesson}
     */
    private function createProjectWithLessonAndRun(): array
    {
        ProjectTag::query()->firstOrCreate(['slug' => 'default'], ['description' => null]);

        $folder = $this->createFolder();
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
            'settings' => [
                'url' => 'https://www.youtube.com/watch?v=abc123',
            ],
        ]);
        [, $pipelineVersion, $textStepVersion] = $this->createPipelineVersionWithTextStep();

        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'done',
            'state' => [],
        ]);

        $run->steps()->create([
            'step_version_id' => $textStepVersion->id,
            'position' => 1,
            'status' => 'done',
            'result' => '# Result',
        ]);

        return [$project, $lesson];
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
}
