<?php

namespace Tests\Unit\Pipeline;

use App\Actions\Pipeline\GetPipelineVersionOptionsAction;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetPipelineVersionOptionsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_current_active_pipeline_versions(): void
    {
        $pipelineWithHistory = Pipeline::query()->create();
        $oldVersion = $pipelineWithHistory->versions()->create([
            'version' => 1,
            'title' => 'Старая версия',
            'description' => null,
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

        $pipelineWithUntitledCurrent = Pipeline::query()->create();
        $untitledCurrentVersion = $pipelineWithUntitledCurrent->versions()->create([
            'version' => 5,
            'title' => '',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);
        $pipelineWithUntitledCurrent->update(['current_version_id' => $untitledCurrentVersion->id]);

        $options = app(GetPipelineVersionOptionsAction::class)->handle();

        $this->assertSame([
            [
                'id' => $currentVersion->id,
                'label' => 'Текущая версия • v2',
                'description' => 'Описание текущей версии',
            ],
            [
                'id' => $untitledCurrentVersion->id,
                'label' => 'Без названия • v5',
                'description' => null,
            ],
        ], $options);

        $this->assertNotContains([
            'id' => $oldVersion->id,
            'label' => 'Старая версия • v1',
            'description' => null,
        ], $options);
        $this->assertNotContains([
            'id' => $archivedCurrentVersion->id,
            'label' => 'Архивная версия • v3',
            'description' => null,
        ], $options);
    }

    public function test_it_hides_pipeline_version_suffix_for_zero_access_level_user(): void
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

        $user = User::factory()->create([
            'access_level' => User::ACCESS_LEVEL_USER,
        ]);

        $options = app(GetPipelineVersionOptionsAction::class)->handle($user);

        $this->assertSame([
            [
                'id' => $currentVersion->id,
                'label' => 'Пайплайн без номера версии',
                'description' => 'Описание версии',
            ],
        ], $options);
    }
}
