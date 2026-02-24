<?php

namespace Tests\Unit\Lesson;

use App\Actions\Lesson\UpdateLessonAudioDurationAction;
use App\Models\Lesson;
use App\Models\Project;
use App\Models\ProjectTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use RuntimeException;
use Tests\TestCase;

class UpdateLessonAudioDurationActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_updates_lesson_duration_setting_using_ffprobe(): void
    {
        $lesson = $this->createLesson('lessons/101.mp3');

        $ffprobe = Mockery::mock();
        $ffprobe->shouldReceive('open')->once()->with('lessons/101.mp3')->andReturnSelf();
        $ffprobe->shouldReceive('getDurationInSeconds')->once()->andReturn(5415);

        FFMpeg::shouldReceive('fromDisk')->once()->with('local')->andReturn($ffprobe);

        $durationSeconds = app(UpdateLessonAudioDurationAction::class)->handle($lesson);

        $this->assertSame(5415, $durationSeconds);
        $this->assertSame(
            5415,
            data_get($lesson->fresh()->settings, UpdateLessonAudioDurationAction::LESSON_DURATION_SETTING_KEY)
        );
    }

    public function test_it_sets_null_duration_when_ffprobe_fails(): void
    {
        $lesson = $this->createLesson('lessons/202.mp3');

        $ffprobe = Mockery::mock();
        $ffprobe->shouldReceive('open')->once()->with('lessons/202.mp3')->andThrow(new RuntimeException('ffprobe failed'));

        FFMpeg::shouldReceive('fromDisk')->once()->with('local')->andReturn($ffprobe);

        $durationSeconds = app(UpdateLessonAudioDurationAction::class)->handle($lesson);

        $this->assertNull($durationSeconds);
        $this->assertNull(
            data_get($lesson->fresh()->settings, UpdateLessonAudioDurationAction::LESSON_DURATION_SETTING_KEY)
        );
    }

    public function test_it_returns_null_when_lesson_has_no_source_file(): void
    {
        $lesson = $this->createLesson(null);

        FFMpeg::shouldReceive('fromDisk')->never();

        $durationSeconds = app(UpdateLessonAudioDurationAction::class)->handle($lesson);

        $this->assertNull($durationSeconds);
        $this->assertNull(
            data_get($lesson->fresh()->settings, UpdateLessonAudioDurationAction::LESSON_DURATION_SETTING_KEY)
        );
    }

    private function createLesson(?string $sourceFilename): Lesson
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект',
            'tags' => null,
        ]);

        return Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок',
            'tag' => 'default',
            'source_filename' => $sourceFilename,
            'settings' => [],
        ]);
    }
}
