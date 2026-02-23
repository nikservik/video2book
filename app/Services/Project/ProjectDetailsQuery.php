<?php

namespace App\Services\Project;

use App\Models\Project;

class ProjectDetailsQuery
{
    public const LESSON_SORT_CREATED_AT = 'created_at';

    public const LESSON_SORT_NAME = 'name';

    public function get(Project $project, string $lessonSort = self::LESSON_SORT_CREATED_AT): Project
    {
        $lessonSort = $this->normalizeLessonSort($lessonSort);

        return $project->load([
            'lessons' => fn ($query) => $query
                ->with([
                    'pipelineRuns' => fn ($runQuery) => $runQuery
                        ->with([
                            'pipelineVersion:id,title,version',
                        ])
                        ->orderByDesc('id')
                        ->select(['id', 'lesson_id', 'pipeline_version_id', 'status']),
                ])
                ->when(
                    $lessonSort === self::LESSON_SORT_NAME,
                    fn ($lessonQuery) => $lessonQuery->when(
                        $lessonQuery->getConnection()->getDriverName() === 'pgsql',
                        fn ($pgsqlQuery) => $pgsqlQuery
                            ->orderByRaw('name COLLATE "numeric"')
                            ->orderBy('id'),
                        fn ($defaultQuery) => $defaultQuery
                            ->orderByRaw('LOWER(name)')
                            ->orderBy('name')
                            ->orderBy('id')
                    ),
                    fn ($lessonQuery) => $lessonQuery
                        ->orderBy('created_at')
                        ->orderBy('id')
                )
                ->select(['id', 'project_id', 'name', 'source_filename', 'settings', 'created_at']),
        ]);
    }

    private function normalizeLessonSort(string $lessonSort): string
    {
        return in_array($lessonSort, [self::LESSON_SORT_CREATED_AT, self::LESSON_SORT_NAME], true)
            ? $lessonSort
            : self::LESSON_SORT_CREATED_AT;
    }
}
