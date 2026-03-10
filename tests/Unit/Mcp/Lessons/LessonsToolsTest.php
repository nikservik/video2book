<?php

namespace Tests\Unit\Mcp\Lessons;

use App\Jobs\DownloadLessonAudioJob;
use App\Jobs\NormalizeUploadedLessonAudioJob;
use App\Mcp\Servers\Video2BookServer;
use App\Mcp\Tools\Lessons\AddProjectLessonsFromListTool;
use App\Mcp\Tools\Lessons\CreateProjectLessonFromAudioTool;
use App\Mcp\Tools\Lessons\CreateProjectLessonFromUrlTool;
use App\Mcp\Tools\Lessons\ListProjectLessonsTool;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class LessonsToolsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_list_project_lessons_tool_returns_project_lessons(): void
    {
        $viewer = $this->makeUser();
        [$project, $lesson] = $this->createProjectWithLessonAndRun();

        Video2BookServer::actingAs($viewer)
            ->tool(ListProjectLessonsTool::class, [
                'project_id' => $project->id,
            ])
            ->assertOk()
            ->assertSee([$project->name, $lesson->name]);
    }

    public function test_create_project_lesson_from_url_tool_creates_lesson_and_queues_download(): void
    {
        Queue::fake();

        $viewer = $this->makeUser();
        $folder = $this->createFolder();
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Project',
            'tags' => null,
        ]);
        [, $pipelineVersion] = $this->createPipelineVersionWithTextStep();

        Video2BookServer::actingAs($viewer)
            ->tool(CreateProjectLessonFromUrlTool::class, [
                'project_id' => $project->id,
                'lesson_name' => 'YouTube Lesson',
                'youtube_url' => 'https://youtube.com/watch?v=abc',
                'pipeline_version_id' => $pipelineVersion->id,
            ])
            ->assertOk()
            ->assertSee('YouTube Lesson');

        $lesson = Lesson::query()->firstWhere('name', 'YouTube Lesson');

        $this->assertNotNull($lesson);
        $this->assertSame('queued', data_get($lesson->settings, 'download_status'));

        Queue::assertPushedOn(DownloadLessonAudioJob::QUEUE, DownloadLessonAudioJob::class);
    }

    public function test_create_project_lesson_from_audio_tool_creates_lesson_and_queues_normalization(): void
    {
        Queue::fake();

        $viewer = $this->makeUser();
        $folder = $this->createFolder();
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Project',
            'tags' => null,
        ]);
        [, $pipelineVersion] = $this->createPipelineVersionWithTextStep();

        Video2BookServer::actingAs($viewer)
            ->tool(CreateProjectLessonFromAudioTool::class, [
                'project_id' => $project->id,
                'lesson_name' => 'Audio Lesson',
                'pipeline_version_id' => $pipelineVersion->id,
                'filename' => 'lesson.mp3',
                'mime_type' => 'audio/mpeg',
                'content_base64' => base64_encode('fake-audio-content'),
            ])
            ->assertOk()
            ->assertSee('Audio Lesson');

        $lesson = Lesson::query()->firstWhere('name', 'Audio Lesson');

        $this->assertNotNull($lesson);
        $this->assertSame('queued', data_get($lesson->settings, 'download_status'));

        Queue::assertPushedOn(NormalizeUploadedLessonAudioJob::QUEUE, NormalizeUploadedLessonAudioJob::class);
        $this->assertSame([], glob(storage_path('app/tmp/mcp-uploads/*')) ?: []);
    }

    public function test_create_project_lesson_from_audio_tool_rejects_non_audio_mime_type(): void
    {
        Queue::fake();

        $viewer = $this->makeUser();
        $folder = $this->createFolder();
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Project',
            'tags' => null,
        ]);
        [, $pipelineVersion] = $this->createPipelineVersionWithTextStep();

        Video2BookServer::actingAs($viewer)
            ->tool(CreateProjectLessonFromAudioTool::class, [
                'project_id' => $project->id,
                'lesson_name' => 'Bad Audio Lesson',
                'pipeline_version_id' => $pipelineVersion->id,
                'filename' => 'lesson.txt',
                'mime_type' => 'text/plain',
                'content_base64' => base64_encode('not-audio'),
            ])
            ->assertHasErrors(['MIME-тип']);

        $this->assertDatabaseMissing('lessons', [
            'project_id' => $project->id,
            'name' => 'Bad Audio Lesson',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_add_project_lessons_from_list_tool_adds_multiple_lessons(): void
    {
        Queue::fake();

        $viewer = $this->makeUser();
        $folder = $this->createFolder();
        [, $pipelineVersion] = $this->createPipelineVersionWithTextStep();
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Project',
            'tags' => null,
            'default_pipeline_version_id' => $pipelineVersion->id,
        ]);

        Video2BookServer::actingAs($viewer)
            ->tool(AddProjectLessonsFromListTool::class, [
                'project_id' => $project->id,
                'lessons_list' => "Lesson A\nhttps://youtube.com/watch?v=1\n\nLesson B\nhttps://youtube.com/watch?v=2",
            ])
            ->assertOk()
            ->assertSee('Project');

        $this->assertSame(2, $project->fresh()->lessons()->count());
        Queue::assertPushed(DownloadLessonAudioJob::class, 2);
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
            'settings' => [],
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
