<?php

namespace Tests\Unit\Project;

use App\Actions\Project\GetProjectExportPipelineStepOptionsAction;
use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Models\PipelineVersionStep;
use App\Models\Project;
use App\Models\ProjectTag;
use App\Models\Step;
use App\Models\StepVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetProjectExportPipelineStepOptionsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_done_pipeline_versions_and_only_text_steps(): void
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Проект',
            'tags' => null,
        ]);

        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Урок',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        [, $doneVersion, $doneTextStep] = $this->createPipelineVersionWithSteps([
            ['name' => 'Транскрибация', 'type' => 'transcribe'],
            ['name' => 'Текстовый шаг', 'type' => 'text'],
            ['name' => 'Глоссарий', 'type' => 'glossary'],
        ]);

        [, $queuedVersion] = $this->createPipelineVersionWithSteps([
            ['name' => 'Текстовый шаг queued', 'type' => 'text'],
        ]);

        PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $doneVersion->id,
            'status' => 'done',
            'state' => [],
        ]);
        PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $queuedVersion->id,
            'status' => 'queued',
            'state' => [],
        ]);

        $options = app(GetProjectExportPipelineStepOptionsAction::class)->handle($project);

        $this->assertCount(1, $options);
        $this->assertSame($doneVersion->id, $options[0]['id']);
        $this->assertCount(1, $options[0]['steps']);
        $this->assertSame($doneTextStep->id, $options[0]['steps'][0]['id']);
        $this->assertSame('Текстовый шаг', $options[0]['steps'][0]['name']);
    }

    /**
     * @param  array<int, array{name:string,type:string}>  $steps
     * @return array{0: Pipeline, 1: \App\Models\PipelineVersion, 2: StepVersion}
     */
    private function createPipelineVersionWithSteps(array $steps): array
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

        $textStepVersion = null;

        foreach ($steps as $index => $stepData) {
            $step = Step::query()->create([
                'pipeline_id' => $pipeline->id,
                'current_version_id' => null,
            ]);

            $stepVersion = StepVersion::query()->create([
                'step_id' => $step->id,
                'input_step_id' => null,
                'name' => $stepData['name'],
                'type' => $stepData['type'],
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
                'position' => $index + 1,
            ]);

            if ($stepData['type'] === 'text') {
                $textStepVersion = $stepVersion;
            }
        }

        return [$pipeline, $version, $textStepVersion];
    }
}
