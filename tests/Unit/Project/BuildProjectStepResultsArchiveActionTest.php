<?php

namespace Tests\Unit\Project;

use App\Actions\Project\BuildProjectStepResultsArchiveAction;
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
use ZipArchive;

class BuildProjectStepResultsArchiveActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_markdown_zip_and_skips_unprocessed_lessons(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект Архив',
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
            'result' => '# Урок 1',
        ]);
        PipelineRunStep::query()->create([
            'pipeline_run_id' => $runTwo->id,
            'step_version_id' => $textStepVersion->id,
            'position' => 1,
            'status' => 'done',
            'result' => '# Урок 2',
        ]);
        PipelineRunStep::query()->create([
            'pipeline_run_id' => $runThree->id,
            'step_version_id' => $textStepVersion->id,
            'position' => 1,
            'status' => 'pending',
            'result' => null,
        ]);

        $archive = app(BuildProjectStepResultsArchiveAction::class)->handle(
            project: $project,
            pipelineVersionId: $pipelineVersion->id,
            stepVersionId: $textStepVersion->id,
            format: 'md',
        );

        $this->assertFileExists($archive['archive_path']);

        $zip = new ZipArchive;
        $zip->open($archive['archive_path']);

        $expectedFileOne = $lessonOne->name.'-'.$textStepVersion->name.'.md';
        $expectedFileTwo = $lessonTwo->name.'-'.$textStepVersion->name.'.md';

        $this->assertSame(2, $zip->numFiles);
        $this->assertNotFalse($zip->locateName($expectedFileOne));
        $this->assertNotFalse($zip->locateName($expectedFileTwo));
        $this->assertSame('# Урок 1', $zip->getFromName($expectedFileOne));
        $this->assertSame('# Урок 2', $zip->getFromName($expectedFileTwo));

        $zip->close();
        File::deleteDirectory($archive['cleanup_dir']);
    }

    public function test_it_builds_docx_zip(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект DOCX',
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
            'result' => "# Урок 1\n\n- Пункт",
        ]);

        $archive = app(BuildProjectStepResultsArchiveAction::class)->handle(
            project: $project,
            pipelineVersionId: $pipelineVersion->id,
            stepVersionId: $textStepVersion->id,
            format: 'docx',
        );

        $this->assertFileExists($archive['archive_path']);

        $zip = new ZipArchive;
        $zip->open($archive['archive_path']);

        $expectedFile = $lesson->name.'-'.$textStepVersion->name.'.docx';

        $this->assertSame(1, $zip->numFiles);
        $this->assertNotFalse($zip->locateName($expectedFile));
        $this->assertStringStartsWith('PK', (string) $zip->getFromName($expectedFile));

        $zip->close();
        File::deleteDirectory($archive['cleanup_dir']);
    }

    public function test_it_throws_validation_exception_when_no_processed_results_found(): void
    {
        $this->expectException(ValidationException::class);

        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Пустой экспорт',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок',
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
            'status' => 'pending',
            'result' => null,
        ]);

        app(BuildProjectStepResultsArchiveAction::class)->handle(
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
        $version = $pipeline->versions()->create([
            'version' => 1,
            'title' => 'Пайплайн',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);
        $pipeline->update(['current_version_id' => $version->id]);

        $step = Step::query()->create([
            'pipeline_id' => $pipeline->id,
            'current_version_id' => null,
        ]);

        $stepVersion = StepVersion::query()->create([
            'step_id' => $step->id,
            'input_step_id' => null,
            'name' => 'Текстовый шаг',
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
            'pipeline_version_id' => $version->id,
            'step_version_id' => $stepVersion->id,
            'position' => 1,
        ]);

        return [$pipeline, $version, $stepVersion];
    }
}
