<?php

namespace Tests\Unit\Pipeline;

use App\Actions\Pipeline\GetPipelineVersionOptionsAction;
use App\Models\Pipeline;
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
            'description' => null,
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
            ],
            [
                'id' => $untitledCurrentVersion->id,
                'label' => 'Без названия • v5',
            ],
        ], $options);

        $this->assertNotContains([
            'id' => $oldVersion->id,
            'label' => 'Старая версия • v1',
        ], $options);
        $this->assertNotContains([
            'id' => $archivedCurrentVersion->id,
            'label' => 'Архивная версия • v3',
        ], $options);
    }
}
