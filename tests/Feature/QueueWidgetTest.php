<?php

namespace Tests\Feature;

use App\Jobs\DownloadLessonAudioJob;
use App\Jobs\ProcessPipelineJob;
use App\Livewire\Widgets\QueueWidget;
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
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class QueueWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_widget_shows_pipeline_and_download_jobs_from_jobs_queue(): void
    {
        $tag = ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект очереди',
            'tags' => null,
        ]);

        [$pipeline, $pipelineVersion] = $this->createPipelineWithSteps();

        $pipelineLesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок обработки',
            'tag' => $tag->slug,
            'settings' => [],
            'source_filename' => null,
        ]);

        $pipelineRun = PipelineRun::query()->create([
            'lesson_id' => $pipelineLesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'running',
            'state' => [],
        ]);

        $stepVersionIds = $pipeline->steps()
            ->with('currentVersion')
            ->orderBy('id')
            ->get()
            ->pluck('currentVersion.id')
            ->values()
            ->all();

        PipelineRunStep::query()->create([
            'pipeline_run_id' => $pipelineRun->id,
            'step_version_id' => $stepVersionIds[0],
            'position' => 1,
            'status' => 'done',
        ]);

        PipelineRunStep::query()->create([
            'pipeline_run_id' => $pipelineRun->id,
            'step_version_id' => $stepVersionIds[1],
            'position' => 2,
            'status' => 'running',
        ]);

        $downloadLesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок скачивания',
            'tag' => $tag->slug,
            'settings' => [
                'download_status' => 'running',
                'download_progress' => 42.5,
            ],
            'source_filename' => null,
        ]);

        $downloadRun = PipelineRun::query()->create([
            'lesson_id' => $downloadLesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'queued',
            'state' => [],
        ]);

        PipelineRunStep::query()->create([
            'pipeline_run_id' => $downloadRun->id,
            'step_version_id' => $stepVersionIds[0],
            'position' => 1,
            'status' => 'done',
        ]);

        PipelineRunStep::query()->create([
            'pipeline_run_id' => $downloadRun->id,
            'step_version_id' => $stepVersionIds[1],
            'position' => 2,
            'status' => 'pending',
        ]);

        DB::table('jobs')->insert([
            [
                'queue' => ProcessPipelineJob::QUEUE,
                'payload' => json_encode([
                    'displayName' => ProcessPipelineJob::class,
                    'data' => [
                        'commandName' => ProcessPipelineJob::class,
                        'command' => 's:13:"pipelineRunId";i:'.$pipelineRun->id.';',
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'attempts' => 0,
                'reserved_at' => now()->timestamp,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ],
            [
                'queue' => DownloadLessonAudioJob::QUEUE,
                'payload' => json_encode([
                    'displayName' => DownloadLessonAudioJob::class,
                    'data' => [
                        'commandName' => DownloadLessonAudioJob::class,
                        'command' => 's:8:"lessonId";i:'.$downloadLesson->id.';',
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ],
        ]);

        $response = $this->get(route('home'));

        $response
            ->assertStatus(200)
            ->assertSee('Очередь обработки')
            ->assertSee('mx-2 md:mx-4 text-lg font-semibold text-gray-900 dark:text-white">Очередь обработки</h2>', false)
            ->assertSee('wire:poll.2s', false)
            ->assertSee('data-queue-task', false)
            ->assertSee('rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-800', false)
            ->assertSee('Урок обработки')
            ->assertSee('Урок скачивания')
            ->assertSee('Пайплайн виджета • v5')
            ->assertDontSee('Шаг 1')
            ->assertDontSee('Шаг 2')
            ->assertDontSee('Прогресс скачивания')
            ->assertSee("wire:click=\"toggleTask('pipeline:", false)
            ->assertSee("wire:click=\"toggleTask('download:", false)
            ->assertSee('1/2')
            ->assertSee('shrink-0 text-indigo-600 dark:text-indigo-400', false)
            ->assertSee('shrink-0 text-gray-500 dark:text-gray-400', false);
    }

    public function test_queue_task_expansion_state_is_kept_between_refreshes(): void
    {
        Livewire::test(QueueWidget::class)
            ->assertSet('expandedTaskKeys', [])
            ->call('toggleTask', 'pipeline:123')
            ->assertSet('expandedTaskKeys', ['pipeline:123'])
            ->call('$refresh')
            ->assertSet('expandedTaskKeys', ['pipeline:123'])
            ->call('toggleTask', 'pipeline:123')
            ->assertSet('expandedTaskKeys', []);
    }

    public function test_empty_queue_message_is_rendered_as_task_card(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertStatus(200)
            ->assertSee('Очередь сейчас пуста.')
            ->assertSee('rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-800', false)
            ->assertDontSee('wire:poll.2s class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-800"', false);
    }

    public function test_widget_shows_only_first_five_tasks_and_more_counter(): void
    {
        $tag = ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект лимита',
            'tags' => null,
        ]);

        [$pipeline, $pipelineVersion] = $this->createPipelineWithSteps();

        $stepVersionIds = $pipeline->steps()
            ->with('currentVersion')
            ->orderBy('id')
            ->get()
            ->pluck('currentVersion.id')
            ->values()
            ->all();

        $now = now();
        $jobs = [];

        for ($index = 1; $index <= 6; $index++) {
            $lesson = Lesson::query()->create([
                'project_id' => $project->id,
                'name' => 'Лимит урок '.$index,
                'tag' => $tag->slug,
                'settings' => [],
                'source_filename' => null,
            ]);

            $run = PipelineRun::query()->create([
                'lesson_id' => $lesson->id,
                'pipeline_version_id' => $pipelineVersion->id,
                'status' => 'queued',
                'state' => [],
            ]);

            PipelineRunStep::query()->create([
                'pipeline_run_id' => $run->id,
                'step_version_id' => $stepVersionIds[0],
                'position' => 1,
                'status' => 'pending',
            ]);

            PipelineRunStep::query()->create([
                'pipeline_run_id' => $run->id,
                'step_version_id' => $stepVersionIds[1],
                'position' => 2,
                'status' => 'pending',
            ]);

            $jobs[] = [
                'queue' => ProcessPipelineJob::QUEUE,
                'payload' => json_encode([
                    'displayName' => ProcessPipelineJob::class,
                    'data' => [
                        'commandName' => ProcessPipelineJob::class,
                        'command' => 's:13:"pipelineRunId";i:'.$run->id.';',
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $now->copy()->addSeconds($index)->timestamp,
                'created_at' => $now->copy()->addSeconds($index)->timestamp,
            ];
        }

        DB::table('jobs')->insert($jobs);

        $response = $this->get(route('home'));

        $response
            ->assertStatus(200)
            ->assertSeeInOrder([
                'Лимит урок 1',
                'Лимит урок 2',
                'Лимит урок 3',
                'Лимит урок 4',
                'Лимит урок 5',
            ])
            ->assertDontSee('Лимит урок 6')
            ->assertSee('Ещё 1 задач');
    }

    /**
     * @return array{0: Pipeline, 1: PipelineVersion}
     */
    private function createPipelineWithSteps(): array
    {
        $pipeline = Pipeline::query()->create();

        $version = $pipeline->versions()->create([
            'version' => 5,
            'title' => 'Пайплайн виджета',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);

        $pipeline->update(['current_version_id' => $version->id]);

        $this->createStep($pipeline, $version, 1, 'transcribe');
        $this->createStep($pipeline, $version, 2, 'text');

        return [$pipeline, $version];
    }

    private function createStep(Pipeline $pipeline, PipelineVersion $version, int $position, string $type): void
    {
        $step = Step::query()->create([
            'pipeline_id' => $pipeline->id,
            'current_version_id' => null,
        ]);

        $stepVersion = StepVersion::query()->create([
            'step_id' => $step->id,
            'input_step_id' => null,
            'name' => 'Шаг '.$position,
            'type' => $type,
            'version' => 1,
            'description' => null,
            'prompt' => null,
            'settings' => [],
            'status' => 'active',
        ]);

        $step->update(['current_version_id' => $stepVersion->id]);

        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $version->id,
            'step_version_id' => $stepVersion->id,
            'position' => $position,
        ]);
    }
}
