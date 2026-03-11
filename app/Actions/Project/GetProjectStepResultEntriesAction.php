<?php

namespace App\Actions\Project;

use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GetProjectStepResultEntriesAction
{
    /**
     * @return Collection<int, array{lesson_name:string, run:PipelineRun, step:PipelineRunStep}>
     */
    public function handle(Project $project, int $pipelineVersionId, int $stepVersionId): Collection
    {
        $lessons = $project->lessons()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'name']);

        $lessonIds = $lessons->pluck('id')->all();

        $latestRunsByLesson = PipelineRun::query()
            ->whereIn('lesson_id', $lessonIds)
            ->where('pipeline_version_id', $pipelineVersionId)
            ->where('status', 'done')
            ->with([
                'lesson:id,name',
                'steps' => fn ($query) => $query
                    ->where('step_version_id', $stepVersionId)
                    ->where('status', 'done')
                    ->whereNotNull('result')
                    ->with('stepVersion:id,name,type')
                    ->orderBy('position')
                    ->orderBy('id'),
            ])
            ->orderByDesc('id')
            ->get()
            ->groupBy('lesson_id')
            ->map(fn ($runs) => $runs->first());

        $entries = $lessons
            ->map(function ($lesson) use ($latestRunsByLesson): ?array {
                $run = $latestRunsByLesson->get($lesson->id);

                if ($run === null) {
                    return null;
                }

                /** @var PipelineRunStep|null $step */
                $step = $run->steps->first();

                if ($step === null || blank($step->result)) {
                    return null;
                }

                return [
                    'lesson_name' => $run->lesson?->name ?? $lesson->name,
                    'run' => $run,
                    'step' => $step,
                ];
            })
            ->filter()
            ->values();

        if ($entries->isEmpty()) {
            throw ValidationException::withMessages([
                'projectExportSelection' => 'Для выбранного шага пока нет обработанных результатов в уроках проекта.',
            ]);
        }

        return $entries;
    }
}
