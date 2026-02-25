<?php

namespace App\Actions\Pipeline;

use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RestorePipelineRunStepOriginalResultAction
{
    public function handle(PipelineRun $pipelineRun, int $stepId, User $user): PipelineRunStep
    {
        /** @var PipelineRunStep $step */
        $step = DB::transaction(function () use ($pipelineRun, $stepId): PipelineRunStep {
            /** @var PipelineRunStep|null $lockedStep */
            $lockedStep = PipelineRunStep::query()
                ->where('pipeline_run_id', $pipelineRun->id)
                ->whereKey($stepId)
                ->lockForUpdate()
                ->first();

            abort_if($lockedStep === null, 404, 'Шаг не принадлежит указанному прогону.');
            abort_if(blank($lockedStep->original), 422, 'Для выбранного шага нет исходного текста.');

            $lockedStep->result = (string) $lockedStep->original;
            $lockedStep->save();

            return $lockedStep;
        });

        $pipelineRun->loadMissing(['lesson.project']);

        $userName = trim((string) $user->name);
        $lessonName = (string) ($pipelineRun->lesson?->name ?? 'Урок');
        $projectName = (string) ($pipelineRun->lesson?->project?->name ?? 'Проект');

        $description = sprintf(
            '%s восстановил текст в шаге %d в уроке «%s» проекта «%s»',
            $userName !== '' ? $userName : "Пользователь #{$user->id}",
            (int) $step->position,
            $lessonName,
            $projectName,
        );

        activity('pipeline-runs')
            ->performedOn($pipelineRun)
            ->causedBy($user)
            ->event('updated')
            ->withProperties([
                'context' => 'pipeline-run-step-result-restored',
                'step_id' => (int) $step->id,
                'step_number' => (int) $step->position,
            ])
            ->log($description);

        return $step;
    }
}
