<?php

namespace App\Services\Project;

use App\Models\Lesson;
use App\Models\PipelineRun;
use Illuminate\Support\Collection;

class LessonRunsQuery
{
    /**
     * @return Collection<int, PipelineRun>
     */
    public function get(Lesson $lesson): Collection
    {
        return PipelineRun::query()
            ->where('lesson_id', $lesson->id)
            ->with([
                'pipelineVersion:id,title,version',
                'steps' => fn ($query) => $query
                    ->with('stepVersion:id,name,type')
                    ->orderBy('position')
                    ->orderBy('id'),
            ])
            ->orderByDesc('id')
            ->get();
    }
}
