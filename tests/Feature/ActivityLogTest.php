<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Models\PipelineVersion;
use App\Models\Project;
use App\Models\ProjectTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_project_create_update_and_delete_with_causer_and_subject(): void
    {
        $user = User::query()->firstOrFail();

        $project = Project::query()->create([
            'name' => 'Project Alpha',
            'tags' => 'edu',
            'settings' => ['lessons_sort' => 'created_at'],
        ]);

        $this->assertActivityLogged(
            logName: 'projects',
            event: 'created',
            subjectType: Project::class,
            subjectId: $project->id,
            causer: $user,
        );

        $project->update([
            'name' => 'Project Beta',
            'tags' => 'edu,livewire',
        ]);

        $this->assertActivityLogged(
            logName: 'projects',
            event: 'updated',
            subjectType: Project::class,
            subjectId: $project->id,
            causer: $user,
        );

        $projectId = $project->id;
        $project->delete();

        $this->assertActivityLogged(
            logName: 'projects',
            event: 'deleted',
            subjectType: Project::class,
            subjectId: $projectId,
            causer: $user,
        );
    }

    public function test_it_logs_lesson_create_update_and_delete_with_causer_and_subject(): void
    {
        $user = User::query()->firstOrFail();
        $project = Project::query()->create(['name' => 'Project for lessons']);
        ProjectTag::query()->firstOrCreate(['slug' => 'default'], ['description' => null]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson 1',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => ['quality' => 'low'],
        ]);

        $this->assertActivityLogged(
            logName: 'lessons',
            event: 'created',
            subjectType: Lesson::class,
            subjectId: $lesson->id,
            causer: $user,
        );

        $lesson->update([
            'name' => 'Lesson 1 updated',
            'settings' => ['quality' => 'high'],
        ]);

        $this->assertActivityLogged(
            logName: 'lessons',
            event: 'updated',
            subjectType: Lesson::class,
            subjectId: $lesson->id,
            causer: $user,
        );

        $lessonId = $lesson->id;
        $lesson->delete();

        $this->assertActivityLogged(
            logName: 'lessons',
            event: 'deleted',
            subjectType: Lesson::class,
            subjectId: $lessonId,
            causer: $user,
        );
    }

    public function test_it_logs_pipeline_run_create_and_delete_with_causer_and_subject(): void
    {
        $user = User::query()->firstOrFail();
        $project = Project::query()->create(['name' => 'Project for runs']);
        ProjectTag::query()->firstOrCreate(['slug' => 'default'], ['description' => null]);
        $pipelineVersion = $this->createPipelineVersion();

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson for runs',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => ['quality' => 'low'],
        ]);

        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'queued',
            'state' => [],
        ]);

        $this->assertActivityLogged(
            logName: 'pipeline-runs',
            event: 'created',
            subjectType: PipelineRun::class,
            subjectId: $run->id,
            causer: $user,
        );

        $runId = $run->id;
        $run->delete();

        $this->assertActivityLogged(
            logName: 'pipeline-runs',
            event: 'deleted',
            subjectType: PipelineRun::class,
            subjectId: $runId,
            causer: $user,
        );
    }

    public function test_it_does_not_log_model_events_without_authenticated_user(): void
    {
        auth()->logout();
        $this->assertNull(auth()->user());

        ProjectTag::query()->firstOrCreate(['slug' => 'default'], ['description' => null]);

        $project = Project::query()->create([
            'name' => 'Project without causer',
            'tags' => 'system',
            'settings' => ['lessons_sort' => 'name'],
        ]);

        $project->update([
            'name' => 'Project without causer updated',
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson without causer',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        $lesson->update([
            'name' => 'Lesson without causer updated',
        ]);

        $pipelineVersion = $this->createPipelineVersion();

        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'queued',
            'state' => [],
        ]);

        $run->delete();
        $lesson->delete();
        $project->delete();

        $this->assertSame(0, Activity::query()->count());
    }

    private function assertActivityLogged(
        string $logName,
        string $event,
        string $subjectType,
        int $subjectId,
        User $causer,
    ): void {
        $activityQuery = Activity::query()
            ->where('log_name', $logName)
            ->where('event', $event)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('causer_type', User::class)
            ->where('causer_id', $causer->id);

        $this->assertSame(1, $activityQuery->count());
    }

    private function createPipelineVersion(): PipelineVersion
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

        return $version;
    }
}
