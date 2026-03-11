<?php

namespace Tests\Unit\Project;

use App\Actions\Project\BuildProjectStepResultsSingleFileAction;
use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\PipelineVersionStep;
use App\Models\Project;
use App\Models\ProjectTag;
use App\Models\Step;
use App\Models\StepVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BuildProjectStepResultsSingleFileActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_single_markdown_file_with_lesson_headings_and_shifted_step_headings(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Большой конспект',
            'tags' => null,
        ]);

        $lessonOne = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок 1',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);
        $lessonTwo = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок 2',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);
        $lessonThree = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок 3',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        [, $pipelineVersion, $textStepVersion] = $this->createPipelineWithTextStep();

        $runOne = PipelineRun::query()->create([
            'lesson_id' => $lessonOne->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'done',
            'state' => [],
        ]);
        $runTwo = PipelineRun::query()->create([
            'lesson_id' => $lessonTwo->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'done',
            'state' => [],
        ]);
        $runThree = PipelineRun::query()->create([
            'lesson_id' => $lessonThree->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'done',
            'state' => [],
        ]);

        PipelineRunStep::query()->create([
            'pipeline_run_id' => $runOne->id,
            'step_version_id' => $textStepVersion->id,
            'position' => 1,
            'status' => 'done',
            'result' => "# Заголовок\n\nТекст урока 1\n\n```md\n# Не менять\n```",
        ]);
        PipelineRunStep::query()->create([
            'pipeline_run_id' => $runTwo->id,
            'step_version_id' => $textStepVersion->id,
            'position' => 1,
            'status' => 'done',
            'result' => "## Подзаголовок\n\n- Пункт 1",
        ]);
        PipelineRunStep::query()->create([
            'pipeline_run_id' => $runThree->id,
            'step_version_id' => $textStepVersion->id,
            'position' => 1,
            'status' => 'pending',
            'result' => null,
        ]);

        $download = app(BuildProjectStepResultsSingleFileAction::class)->handle(
            project: $project,
            pipelineVersionId: $pipelineVersion->id,
            stepVersionId: $textStepVersion->id,
            format: 'md',
        );

        $this->assertSame('Большой конспект.md', $download['download_filename']);
        $this->assertSame('text/markdown; charset=UTF-8', $download['content_type']);
        $this->assertFileExists($download['file_path']);
        $this->assertStringContainsString("# Урок 1\n\n## Заголовок", (string) file_get_contents($download['file_path']));
        $this->assertStringContainsString("# Урок 2\n\n### Подзаголовок", (string) file_get_contents($download['file_path']));
        $this->assertStringContainsString("```md\n# Не менять\n```", (string) file_get_contents($download['file_path']));
        $this->assertStringNotContainsString('# Урок 3', (string) file_get_contents($download['file_path']));

        File::deleteDirectory($download['cleanup_dir']);
    }

    public function test_it_builds_single_pdf_file(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Общий PDF',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок 1',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        [, $pipelineVersion, $textStepVersion] = $this->createPipelineWithTextStep();

        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'done',
            'state' => [],
        ]);

        PipelineRunStep::query()->create([
            'pipeline_run_id' => $run->id,
            'step_version_id' => $textStepVersion->id,
            'position' => 1,
            'status' => 'done',
            'result' => "# Заголовок\n\nТекст",
        ]);

        $download = app(BuildProjectStepResultsSingleFileAction::class)->handle(
            project: $project,
            pipelineVersionId: $pipelineVersion->id,
            stepVersionId: $textStepVersion->id,
            format: 'pdf',
        );

        $this->assertSame('Общий PDF.pdf', $download['download_filename']);
        $this->assertSame('application/pdf', $download['content_type']);
        $this->assertStringStartsWith('%PDF', (string) file_get_contents($download['file_path']));

        File::deleteDirectory($download['cleanup_dir']);
    }

    public function test_it_builds_single_docx_file(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Общий DOCX',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок 1',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        [, $pipelineVersion, $textStepVersion] = $this->createPipelineWithTextStep();

        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'done',
            'state' => [],
        ]);

        PipelineRunStep::query()->create([
            'pipeline_run_id' => $run->id,
            'step_version_id' => $textStepVersion->id,
            'position' => 1,
            'status' => 'done',
            'result' => "# Заголовок\n\nТекст",
        ]);

        $download = app(BuildProjectStepResultsSingleFileAction::class)->handle(
            project: $project,
            pipelineVersionId: $pipelineVersion->id,
            stepVersionId: $textStepVersion->id,
            format: 'docx',
        );

        $this->assertSame('Общий DOCX.docx', $download['download_filename']);
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $download['content_type']
        );
        $this->assertStringStartsWith('PK', (string) file_get_contents($download['file_path']));

        File::deleteDirectory($download['cleanup_dir']);
    }

    public function test_it_throws_validation_exception_when_no_processed_results_found(): void
    {
        $this->expectException(ValidationException::class);

        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Пустой проект',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок 1',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        [, $pipelineVersion, $textStepVersion] = $this->createPipelineWithTextStep();

        PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'done',
            'state' => [],
        ]);

        app(BuildProjectStepResultsSingleFileAction::class)->handle(
            project: $project,
            pipelineVersionId: $pipelineVersion->id,
            stepVersionId: $textStepVersion->id,
            format: 'md',
        );
    }

    /**
     * @return array{0: Pipeline, 1: \App\Models\PipelineVersion, 2: StepVersion}
     */
    private function createPipelineWithTextStep(): array
    {
        $pipeline = Pipeline::query()->create();
        $pipelineVersion = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Пайплайн',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);
        $pipeline->update(['current_version_id' => $pipelineVersion->id]);

        $step = Step::query()->create([
            'pipeline_id' => $pipeline->id,
            'current_version_id' => null,
        ]);

        $stepVersion = StepVersion::query()->create([
            'step_id' => $step->id,
            'input_step_id' => null,
            'name' => 'Текстовый экспорт',
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

        $step->update(['current_version_id' => $stepVersion->id]);

        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $pipelineVersion->id,
            'step_version_id' => $stepVersion->id,
            'position' => 1,
        ]);

        return [$pipeline, $pipelineVersion, $stepVersion];
    }
}
