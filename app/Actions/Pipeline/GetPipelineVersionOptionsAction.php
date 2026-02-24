<?php

namespace App\Actions\Pipeline;

use App\Models\Pipeline;
use App\Models\User;

class GetPipelineVersionOptionsAction
{
    /**
     * @return array<int, array{id:int,label:string,description:string|null}>
     */
    public function handle(?User $viewer = null): array
    {
        $viewer ??= auth()->user();
        $showVersionInLabel = ! ($viewer instanceof User && (int) $viewer->access_level === User::ACCESS_LEVEL_USER);

        return Pipeline::query()
            ->whereNotNull('current_version_id')
            ->whereHas('currentVersion', fn ($query) => $query->where('status', 'active'))
            ->with([
                'currentVersion:id,title,description,version,status',
            ])
            ->orderBy('id')
            ->get()
            ->map(function (Pipeline $pipeline) use ($showVersionInLabel): ?array {
                $currentVersion = $pipeline->currentVersion;

                if ($currentVersion === null) {
                    return null;
                }

                $title = trim((string) $currentVersion->title);
                $description = trim((string) $currentVersion->description);

                return [
                    'id' => $currentVersion->id,
                    'label' => $showVersionInLabel
                        ? sprintf(
                            '%s • v%d',
                            $title !== '' ? $title : 'Без названия',
                            $currentVersion->version,
                        )
                        : ($title !== '' ? $title : 'Без названия'),
                    'description' => $description !== '' ? $description : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
