<?php

namespace App\Services\Pipeline;

use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Services\Pipeline\Contracts\PipelineStepExecutor;
use App\Services\Pipeline\PipelineEventBroadcaster;
use App\Services\Pipeline\PipelineStepResult;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class PipelineRunProcessingService
{
    public function __construct(
        private readonly PipelineStepExecutor $executor,
        private readonly PipelineEventBroadcaster $eventBroadcaster,
    ) {
    }

    /**
     * Обрабатывает следующий шаг пайплайна.
     *
     * @return bool true, если остались шаги и нужно поставить задачу снова.
     *
     * @throws Throwable
     */
    public function handle(int $pipelineRunId): bool
    {
        $run = PipelineRun::query()
            ->with(['lesson', 'steps.stepVersion.step'])
            ->findOrFail($pipelineRunId);

        if (in_array($run->status, ['failed', 'done'], true)) {
            return false;
        }

        $step = $this->claimNextStep($run);

        if ($step === null) {
            $this->markRunAsCompleted($run);

            return false;
        }

        $run = $run->fresh(['lesson.project', 'pipelineVersion', 'steps.stepVersion.step']);
        $step = $step->fresh(['stepVersion.step']);

        $this->eventBroadcaster->runUpdated($run);
        $this->eventBroadcaster->queueRunUpdated($run);
        $this->eventBroadcaster->stepUpdated($step);

        try {
            $input = $this->resolveStepInput($run, $step);
            $result = $this->executor->execute($run, $step, $input);
            $this->markStepCompleted($step, $result);
        } catch (Throwable $e) {
            $this->markStepFailed($run, $step, $e);

            throw $e;
        }

        $hasPending = $this->hasPendingSteps($run->id);

        if (! $hasPending) {
            $this->markRunAsCompleted($run->fresh());
        }

        return $hasPending;
    }

    private function claimNextStep(PipelineRun $run): ?PipelineRunStep
    {
        return DB::transaction(function () use ($run) {
            $step = PipelineRunStep::query()
                ->where('pipeline_run_id', $run->id)
                ->whereIn('status', ['pending', 'running'])
                ->orderBy('position')
                ->lockForUpdate()
                ->first();

            if ($step === null) {
                return null;
            }

            if ($step->status === 'pending') {
                $step->forceFill([
                    'status' => 'running',
                    'start_time' => now(),
                ])->save();
            }

            $run->forceFill(['status' => 'running'])->save();

            return $step->fresh(['stepVersion.step']);
        });
    }

    private function resolveStepInput(PipelineRun $run, PipelineRunStep $step): ?string
    {
        $stepVersion = $step->stepVersion;

        if ($stepVersion === null) {
            throw new RuntimeException('Не удалось загрузить версию шага.');
        }

        if ($stepVersion->type === 'transcribe') {
            return null;
        }

        $run->loadMissing('steps.stepVersion.step');

        $inputStep = null;

        if ($stepVersion->input_step_id !== null) {
            $inputStep = $run->steps
                ->first(fn (PipelineRunStep $candidate) => $candidate->stepVersion?->step_id === $stepVersion->input_step_id);
        } else {
            $inputStep = $run->steps
                ->filter(fn (PipelineRunStep $candidate) => $candidate->position < $step->position)
                ->sortByDesc('position')
                ->first();
        }

        if ($inputStep === null || $inputStep->result === null || $inputStep->status !== 'done') {
            throw new RuntimeException('Источник данных для шага недоступен. Убедитесь, что предыдущий шаг завершён.');
        }

        return $inputStep->result;
    }

    private function markStepCompleted(PipelineRunStep $step, PipelineStepResult $result): void
    {
        $step->forceFill([
            'status' => 'done',
            'end_time' => now(),
            'result' => $result->output,
            'error' => null,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'cost' => $result->cost,
        ])->save();

        $step = $step->fresh(['stepVersion.step', 'pipelineRun.lesson.project', 'pipelineRun.pipelineVersion', 'pipelineRun.steps.stepVersion.step']);

        $this->eventBroadcaster->stepUpdated($step);

        $run = $step->pipelineRun;

        if ($run !== null) {
            $this->eventBroadcaster->runUpdated($run);
            $this->eventBroadcaster->queueRunUpdated($run);
        }
    }

    private function markStepFailed(PipelineRun $run, PipelineRunStep $step, Throwable $throwable): void
    {
        DB::transaction(function () use ($run, $step, $throwable): void {
            $step->forceFill([
                'status' => 'failed',
                'end_time' => now(),
                'error' => $throwable->getMessage(),
            ])->save();

            $run->forceFill(['status' => 'failed'])->save();
        });

        $this->eventBroadcaster->stepUpdated($step->fresh(['stepVersion.step']));
        $run = $run->fresh(['lesson.project', 'pipelineVersion', 'steps.stepVersion.step']);
        $this->eventBroadcaster->runUpdated($run);
        $this->eventBroadcaster->queueRunRemoved($run->id);
    }

    private function markRunAsCompleted(PipelineRun $run): void
    {
        $run->forceFill(['status' => 'done'])->save();

        $run = $run->fresh(['lesson.project', 'pipelineVersion', 'steps.stepVersion.step']);

        $this->eventBroadcaster->runUpdated($run);
        $this->eventBroadcaster->queueRunRemoved($run->id);
    }

    private function hasPendingSteps(int $runId): bool
    {
        return PipelineRunStep::query()
            ->where('pipeline_run_id', $runId)
            ->where('status', 'pending')
            ->exists();
    }
}
