<?php

namespace App\Actions\Pipeline;

use App\Models\Pipeline;

class GetPipelineVersionOptionsAction
{
    /**
     * @return array<int, array{id:int,label:string}>
     */
    public function handle(): array
    {
        return Pipeline::query()
            ->whereNotNull('current_version_id')
            ->whereHas('currentVersion', fn ($query) => $query->where('status', 'active'))
            ->with([
                'currentVersion:id,title,version,status',
            ])
            ->orderBy('id')
            ->get()
            ->map(function (Pipeline $pipeline): ?array {
                $currentVersion = $pipeline->currentVersion;

                if ($currentVersion === null) {
                    return null;
                }

                $title = trim((string) $currentVersion->title);

                return [
                    'id' => $currentVersion->id,
                    'label' => sprintf(
                        '%s • v%d',
                        $title !== '' ? $title : 'Без названия',
                        $currentVersion->version,
                    ),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
