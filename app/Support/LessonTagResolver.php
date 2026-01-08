<?php

namespace App\Support;

use App\Models\ProjectTag;

final class LessonTagResolver
{
    public static function resolve(?string $tag): string
    {
        if ($tag !== null) {
            return $tag;
        }

        $defaultSlug = 'default';

        ProjectTag::query()->firstOrCreate(['slug' => $defaultSlug], [
            'description' => null,
        ]);

        return $defaultSlug;
    }
}
