<?php

namespace App\Actions\Pipeline;

use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\User;
use App\Support\StepResultHtmlToMarkdownConverter;
use Illuminate\Support\Facades\DB;
use Mews\Purifier\Facades\Purifier;

class SavePipelineRunStepResultAction
{
    public function handle(PipelineRun $pipelineRun, int $stepId, string $rawHtml, User $user): PipelineRunStep
    {
        $sanitizedHtml = (string) Purifier::clean($rawHtml, 'default');
        $converter = app(StepResultHtmlToMarkdownConverter::class);
        $markdown = trim($converter->convert($sanitizedHtml));

        /** @var PipelineRunStep $step */
        $step = DB::transaction(function () use ($pipelineRun, $stepId, $markdown): PipelineRunStep {
            /** @var PipelineRunStep|null $lockedStep */
            $lockedStep = PipelineRunStep::query()
                ->where('pipeline_run_id', $pipelineRun->id)
                ->whereKey($stepId)
                ->lockForUpdate()
                ->first();

            abort_if($lockedStep === null, 404, 'Шаг не принадлежит указанному прогону.');

            if ($lockedStep->original === null) {
                $lockedStep->original = (string) ($lockedStep->result ?? '');
            }

            $lockedStep->result = $markdown;
            $lockedStep->save();

            return $lockedStep;
        });

        $pipelineRun->loadMissing(['lesson.project']);

        $userName = trim((string) $user->name);
        $lessonName = (string) ($pipelineRun->lesson?->name ?? 'Урок');
        $projectName = (string) ($pipelineRun->lesson?->project?->name ?? 'Проект');

        $description = sprintf(
            '%s изменил текст в шаге %d в уроке «%s» проекта «%s»',
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
                'context' => 'pipeline-run-step-result-edited',
                'step_id' => (int) $step->id,
                'step_number' => (int) $step->position,
            ])
            ->log($description);

        return $step;
    }
}
