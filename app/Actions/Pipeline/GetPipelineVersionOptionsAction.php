<?php

namespace App\Actions\Pipeline;

use App\Models\Pipeline;
use App\Models\PipelineVersion;

class GetPipelineVersionOptionsAction
{
    /**
     * @return array<int, array{id:int,label:string}>
     */
    public function handle(): array
    {
        return Pipeline::query()
            ->with([
                'versions' => fn ($query) => $query
                    ->orderBy('version'),
            ])
            ->orderBy('id')
            ->get()
            ->flatMap(function (Pipeline $pipeline) {
                return $pipeline->versions->map(function (PipelineVersion $version): array {
                    return [
                        'id' => $version->id,
                        'label' => sprintf(
                            '%s • v%d',
                            $version->title ?? 'Без названия',
                            $version->version,
                        ),
                    ];
                });
            })
            ->values()
            ->all();
    }
}
