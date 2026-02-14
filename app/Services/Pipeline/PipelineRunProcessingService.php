<?php

namespace App\Services\Pipeline;

use App\Jobs\ProcessPipelineJob;
use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Services\Llm\Exceptions\HaikuRateLimitExceededException;
use App\Services\Pipeline\Contracts\PipelineStepExecutor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class PipelineRunProcessingService
{
    public function __construct(
        private readonly PipelineStepExecutor $executor,
        private readonly PipelineEventBroadcaster $eventBroadcaster,
    ) {}

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

        if (in_array($run->status, ['failed', 'done', 'paused'], true)) {
            return false;
        }

        $step = $this->claimNextStep($run);

        if ($step === null) {
            if ($this->hasPausedSteps($run->id)) {
                $this->markRunAsPaused($run);
            } else {
                $this->markRunAsCompleted($run);
            }

            return false;
        }

        $run = $run->fresh(['lesson.project', 'pipelineVersion', 'steps.stepVersion.step']);
        $step = $step->fresh(['stepVersion.step']);

        if ($this->shouldInterruptStep($run->id, $step->id)) {
            $this->markRunAsPaused($run);

            return false;
        }

        $this->eventBroadcaster->runUpdated($run);
        $this->eventBroadcaster->queueRunUpdated($run);

        try {
            $input = $this->resolveStepInput($run, $step);
            $result = $this->executor->execute($run, $step, $input);
            $this->markStepCompleted($run->id, $step->id, $result);
        } catch (HaikuRateLimitExceededException $e) {
            $this->deferStepAfterRateLimit($run, $step, $e->retryAfterSeconds());

            return false;
        } catch (Throwable $e) {
            $stepVersion = $step->stepVersion;
            $provider = $stepVersion?->settings['provider'] ?? null;
            $model = $stepVersion?->settings['model'] ?? null;
            Log::error('Pipeline step failed', [
                'run_id' => $run->id,
                'step_id' => $step->id,
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);
            $this->markStepFailed($run, $step, $e);

            return false;
        }

        $hasPending = $this->hasPendingSteps($run->id);

        if (! $hasPending) {
            if ($this->hasPausedSteps($run->id)) {
                $this->markRunAsPaused($run->fresh());
            } else {
                $this->markRunAsCompleted($run->fresh());
            }
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

    private function markStepCompleted(int $runId, int $stepId, PipelineStepResult $result): void
    {
        $shouldPersistResult = DB::transaction(function () use ($runId, $stepId, $result): bool {
            $run = PipelineRun::query()
                ->whereKey($runId)
                ->lockForUpdate()
                ->first();
            $step = PipelineRunStep::query()
                ->whereKey($stepId)
                ->lockForUpdate()
                ->first();

            if ($run === null || $step === null) {
                return false;
            }

            $stopRequested = (bool) data_get($run->state, 'stop_requested', false);

            if ($step->status === 'paused' || $stopRequested) {
                $run->forceFill(['status' => 'paused'])->save();

                return false;
            }

            $step->forceFill([
                'status' => 'done',
                'end_time' => now(),
                'result' => $result->output,
                'error' => null,
                'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens,
                'cost' => $result->cost,
            ])->save();

            return true;
        });

        if (! $shouldPersistResult) {
            return;
        }

        $step = PipelineRunStep::query()
            ->with(['stepVersion.step', 'pipelineRun.lesson.project', 'pipelineRun.pipelineVersion', 'pipelineRun.steps.stepVersion.step'])
            ->find($stepId);

        if ($step === null) {
            return;
        }

        $this->eventBroadcaster->stepCompleted($step);

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

        $this->eventBroadcaster->stepCompleted($step->fresh(['stepVersion.step']));
        $run = $run->fresh(['lesson.project', 'pipelineVersion', 'steps.stepVersion.step']);
        $this->eventBroadcaster->runUpdated($run);
        $this->eventBroadcaster->queueRunRemoved($run->id);
        $this->eventBroadcaster->flushRunStream($run->id);
    }

    private function markRunAsPaused(PipelineRun $run): void
    {
        $run->forceFill(['status' => 'paused'])->save();

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
        $this->eventBroadcaster->flushRunStream($run->id);
    }

    private function hasPendingSteps(int $runId): bool
    {
        return PipelineRunStep::query()
            ->where('pipeline_run_id', $runId)
            ->where('status', 'pending')
            ->exists();
    }

    private function hasPausedSteps(int $runId): bool
    {
        return PipelineRunStep::query()
            ->where('pipeline_run_id', $runId)
            ->where('status', 'paused')
            ->exists();
    }

    private function shouldInterruptStep(int $runId, int $stepId): bool
    {
        $run = PipelineRun::query()->find($runId);
        $step = PipelineRunStep::query()->find($stepId);

        if ($run === null || $step === null) {
            return true;
        }

        return (bool) data_get($run->state, 'stop_requested', false) || $step->status === 'paused';
    }

    private function deferStepAfterRateLimit(PipelineRun $run, PipelineRunStep $step, int $retryAfterSeconds): void
    {
        DB::transaction(function () use ($run, $step): void {
            $step->forceFill([
                'status' => 'pending',
                'start_time' => null,
                'end_time' => null,
            ])->save();

            $run->forceFill(['status' => 'queued'])->save();
        });

        $run = $run->fresh(['lesson.project', 'pipelineVersion', 'steps.stepVersion.step']);

        $this->eventBroadcaster->queueRunUpdated($run);
        $this->eventBroadcaster->runUpdated($run);

        ProcessPipelineJob::dispatch($run->id)
            ->onQueue(ProcessPipelineJob::QUEUE)
            ->delay($retryAfterSeconds);
    }
}
