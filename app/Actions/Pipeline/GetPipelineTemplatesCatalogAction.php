<?php

namespace App\Actions\Pipeline;

use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\StepVersion;
use App\Models\User;

class GetPipelineTemplatesCatalogAction
{
    /**
     * @return array<int, array{
     *     id:int,
     *     name:string,
     *     label:string,
     *     description:string|null,
     *     version:int,
     *     steps:array<int, array{
     *         id:int,
     *         position:int,
     *         name:string,
     *         description:string|null
     *     }>
     * }>
     */
    public function handle(?User $viewer = null): array
    {
        $viewer ??= auth()->user();
        $showVersionInLabel = ! ($viewer instanceof User && (int) $viewer->access_level === User::ACCESS_LEVEL_USER);

        return Pipeline::query()
            ->whereNotNull('current_version_id')
            ->whereHas('currentVersion', fn ($query) => $query->where('status', 'active'))
            ->with([
                'currentVersion:id,pipeline_id,title,description,version,status',
                'currentVersion.versionSteps' => fn ($query) => $query
                    ->select(['id', 'pipeline_version_id', 'step_version_id', 'position'])
                    ->orderBy('position'),
                'currentVersion.versionSteps.stepVersion' => fn ($query) => $query
                    ->select(['id', 'step_id', 'input_step_id', 'name', 'description']),
            ])
            ->orderBy('id')
            ->get()
            ->map(function (Pipeline $pipeline) use ($showVersionInLabel): ?array {
                $currentVersion = $pipeline->currentVersion;

                if (! $currentVersion instanceof PipelineVersion) {
                    return null;
                }

                $name = $this->normalizeName($currentVersion->title);

                return [
                    'id' => $currentVersion->id,
                    'name' => $name,
                    'label' => $showVersionInLabel
                        ? sprintf('%s • v%d', $name, (int) $currentVersion->version)
                        : $name,
                    'description' => $this->normalizeDescription($currentVersion->description),
                    'version' => (int) $currentVersion->version,
                    'steps' => $currentVersion->versionSteps
                        ->map(function (PipelineVersionStep $versionStep): ?array {
                            $stepVersion = $versionStep->stepVersion;

                            if (! $stepVersion instanceof StepVersion) {
                                return null;
                            }

                            return [
                                'id' => $stepVersion->id,
                                'position' => (int) $versionStep->position,
                                'name' => $this->normalizeName($stepVersion->name),
                                'description' => $this->normalizeDescription($stepVersion->description),
                            ];
                        })
                        ->filter()
                        ->values()
                        ->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeName(?string $value): string
    {
        $name = trim((string) $value);

        return $name !== '' ? $name : 'Без названия';
    }

    private function normalizeDescription(?string $value): ?string
    {
        $description = trim((string) $value);

        return $description !== '' ? $description : null;
    }
}
