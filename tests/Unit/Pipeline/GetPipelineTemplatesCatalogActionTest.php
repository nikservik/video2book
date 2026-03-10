<?php

namespace Tests\Unit\Pipeline;

use App\Actions\Pipeline\GetPipelineTemplatesCatalogAction;
use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Step;
use App\Models\StepVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetPipelineTemplatesCatalogActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_current_active_pipeline_versions_with_ordered_steps(): void
    {
        $pipelineWithHistory = Pipeline::query()->create();
        $pipelineWithHistory->versions()->create([
            'version' => 1,
            'title' => 'Старая версия',
            'description' => 'Нужно скрыть',
            'changelog' => null,
            'status' => 'active',
        ]);
        $currentVersion = $pipelineWithHistory->versions()->create([
            'version' => 2,
            'title' => 'Текущая версия',
            'description' => 'Описание текущей версии',
            'changelog' => null,
            'status' => 'active',
        ]);
        $pipelineWithHistory->update(['current_version_id' => $currentVersion->id]);
        $firstStepVersion = $this->attachStep($pipelineWithHistory, $currentVersion, 1, 'Транскрибация', 'Описание транскрибации');
        $secondStepVersion = $this->attachStep($pipelineWithHistory, $currentVersion, 2, 'Конспект', 'Описание конспекта', true);

        $pipelineWithArchivedCurrent = Pipeline::query()->create();
        $archivedCurrentVersion = $pipelineWithArchivedCurrent->versions()->create([
            'version' => 3,
            'title' => 'Архивная версия',
            'description' => null,
            'changelog' => null,
            'status' => 'archived',
        ]);
        $pipelineWithArchivedCurrent->update(['current_version_id' => $archivedCurrentVersion->id]);

        $pipelineWithoutCurrentVersion = Pipeline::query()->create();
        $pipelineWithoutCurrentVersion->versions()->create([
            'version' => 4,
            'title' => 'Без текущей',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);

        $catalog = app(GetPipelineTemplatesCatalogAction::class)->handle();

        $this->assertSame([
            [
                'id' => $currentVersion->id,
                'name' => 'Текущая версия',
                'label' => 'Текущая версия • v2',
                'description' => 'Описание текущей версии',
                'version' => 2,
                'steps' => [
                    [
                        'id' => $firstStepVersion->id,
                        'position' => 1,
                        'name' => 'Транскрибация',
                        'description' => 'Описание транскрибации',
                        'is_default' => false,
                    ],
                    [
                        'id' => $secondStepVersion->id,
                        'position' => 2,
                        'name' => 'Конспект',
                        'description' => 'Описание конспекта',
                        'is_default' => true,
                    ],
                ],
            ],
        ], $catalog);
    }

    public function test_it_hides_version_suffix_for_zero_access_level_user_but_keeps_steps(): void
    {
        $pipeline = Pipeline::query()->create();
        $currentVersion = $pipeline->versions()->create([
            'version' => 7,
            'title' => 'Пайплайн без номера версии',
            'description' => 'Описание версии',
            'changelog' => null,
            'status' => 'active',
        ]);
        $pipeline->update(['current_version_id' => $currentVersion->id]);
        $stepVersion = $this->attachStep($pipeline, $currentVersion, 1, 'Шаг 1', 'Описание шага', true);

        $user = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_USER,
        ]);

        $catalog = app(GetPipelineTemplatesCatalogAction::class)->handle($user);

        $this->assertSame([
            [
                'id' => $currentVersion->id,
                'name' => 'Пайплайн без номера версии',
                'label' => 'Пайплайн без номера версии',
                'description' => 'Описание версии',
                'version' => 7,
                'steps' => [
                    [
                        'id' => $stepVersion->id,
                        'position' => 1,
                        'name' => 'Шаг 1',
                        'description' => 'Описание шага',
                        'is_default' => true,
                    ],
                ],
            ],
        ], $catalog);
    }

    private function attachStep(
        Pipeline $pipeline,
        PipelineVersion $pipelineVersion,
        int $position,
        string $name,
        ?string $description,
        bool $isDefault = false,
    ): StepVersion {
        $step = Step::query()->create([
            'pipeline_id' => $pipeline->id,
            'current_version_id' => null,
        ]);

        $stepVersion = StepVersion::query()->create([
            'step_id' => $step->id,
            'input_step_id' => null,
            'name' => $name,
            'type' => 'text',
            'version' => 1,
            'description' => $description,
            'prompt' => 'Prompt',
            'settings' => [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'is_default' => $isDefault,
            ],
            'status' => 'active',
        ]);

        $step->update(['current_version_id' => $stepVersion->id]);

        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $pipelineVersion->id,
            'step_version_id' => $stepVersion->id,
            'position' => $position,
        ]);

        return $stepVersion;
    }
}
