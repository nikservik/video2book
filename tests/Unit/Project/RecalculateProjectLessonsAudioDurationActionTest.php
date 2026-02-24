<?php

namespace Tests\Unit\Project;

use App\Actions\Lesson\UpdateLessonAudioDurationAction;
use App\Actions\Project\RecalculateProjectLessonsAudioDurationAction;
use App\Models\Lesson;
use App\Models\Project;
use App\Models\ProjectTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Tests\TestCase;

class RecalculateProjectLessonsAudioDurationActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_recalculates_project_total_and_backfills_missing_lesson_durations(): void
    {
        $project = $this->createProject();

        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок 1',
            'tag' => 'default',
            'source_filename' => 'lessons/1.mp3',
            'settings' => [
                UpdateLessonAudioDurationAction::LESSON_DURATION_SETTING_KEY => 3600,
            ],
        ]);

        $lessonWithoutDuration = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок 2',
            'tag' => 'default',
            'source_filename' => 'lessons/2.mp3',
            'settings' => [],
        ]);

        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок 3',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        $ffprobe = Mockery::mock();
        $ffprobe->shouldReceive('open')->once()->with('lessons/2.mp3')->andReturnSelf();
        $ffprobe->shouldReceive('getDurationInSeconds')->once()->andReturn(1800);

        FFMpeg::shouldReceive('fromDisk')->once()->with('local')->andReturn($ffprobe);

        $totalDurationSeconds = app(RecalculateProjectLessonsAudioDurationAction::class)->handle($project);

        $this->assertSame(5400, $totalDurationSeconds);
        $this->assertSame(
            1800,
            data_get(
                $lessonWithoutDuration->fresh()->settings,
                UpdateLessonAudioDurationAction::LESSON_DURATION_SETTING_KEY
            )
        );
        $this->assertSame(
            5400,
            data_get(
                $project->fresh()->settings,
                RecalculateProjectLessonsAudioDurationAction::PROJECT_TOTAL_DURATION_SETTING_KEY
            )
        );
    }

    public function test_it_uses_existing_lesson_durations_without_extra_ffprobe_calls(): void
    {
        $project = $this->createProject();

        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок 1',
            'tag' => 'default',
            'source_filename' => 'lessons/10.mp3',
            'settings' => [
                UpdateLessonAudioDurationAction::LESSON_DURATION_SETTING_KEY => 600,
            ],
        ]);

        Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок 2',
            'tag' => 'default',
            'source_filename' => 'lessons/20.mp3',
            'settings' => [
                UpdateLessonAudioDurationAction::LESSON_DURATION_SETTING_KEY => 300,
            ],
        ]);

        FFMpeg::shouldReceive('fromDisk')->never();

        $totalDurationSeconds = app(RecalculateProjectLessonsAudioDurationAction::class)->handle($project);

        $this->assertSame(900, $totalDurationSeconds);
        $this->assertSame(
            900,
            data_get(
                $project->fresh()->settings,
                RecalculateProjectLessonsAudioDurationAction::PROJECT_TOTAL_DURATION_SETTING_KEY
            )
        );
    }

    private function createProject(): Project
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        return Project::query()->create([
            'name' => 'Проект',
            'tags' => null,
            'settings' => [],
        ]);
    }
}
