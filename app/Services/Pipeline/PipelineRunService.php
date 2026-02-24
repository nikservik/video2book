<?php

namespace App\Services\Pipeline;

use App\Jobs\ProcessPipelineJob;
use App\Models\Lesson;
use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\StepVersion;
use Illuminate\Bus\UniqueLock;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PipelineRunService
{
    public function __construct(private readonly PipelineEventBroadcaster $eventBroadcaster) {}

    /**
     * Создаёт новый прогон шаблона для урока и опционально ставит его в очередь.
     */
    public function createRun(Lesson $lesson, PipelineVersion $pipelineVersion, bool $dispatchJob = true): PipelineRun
    {
        return $this->createRunInternal(
            lesson: $lesson,
            pipelineVersion: $pipelineVersion,
            sourceRun: null,
            dispatchJob: $dispatchJob,
        );
    }

    /**
     * Создаёт новый прогон для версии шаблона и переиспользует результаты
     * неизменённых шагов из последнего завершённого прогона этого же урока.
     */
    public function createRunReusingUnchangedPrefix(Lesson $lesson, PipelineVersion $pipelineVersion, bool $dispatchJob = true): PipelineRun
    {
        $sourceRun = $this->resolveReusableSourceRun($lesson, $pipelineVersion);

        return $this->createRunInternal(
            lesson: $lesson,
            pipelineVersion: $pipelineVersion,
            sourceRun: $sourceRun,
            dispatchJob: $dispatchJob,
        );
    }

    private function createRunInternal(
        Lesson $lesson,
        PipelineVersion $pipelineVersion,
        ?PipelineRun $sourceRun,
        bool $dispatchJob,
    ): PipelineRun {
        $pipelineVersion->loadMissing('versionSteps.stepVersion.step');

        /** @var Collection<int, PipelineVersionStep> $versionSteps */
        $versionSteps = $pipelineVersion->versionSteps->sortBy('position')->values();

        if ($versionSteps->isEmpty()) {
            throw ValidationException::withMessages([
                'pipeline_version_id' => 'Выбранная версия шаблона не содержит шагов.',
            ]);
        }

        $reusablePrefixSteps = $this->resolveReusablePrefixSteps($versionSteps, $sourceRun);
        $allStepsReused = count($reusablePrefixSteps) === $versionSteps->count();

        $run = DB::transaction(function () use ($lesson, $pipelineVersion, $versionSteps, $reusablePrefixSteps, $allStepsReused) {
            $run = $lesson->pipelineRuns()->create([
                'pipeline_version_id' => $pipelineVersion->id,
                'status' => $allStepsReused ? 'done' : 'queued',
                'state' => [],
            ]);

            foreach ($versionSteps as $index => $versionStep) {
                /** @var StepVersion|null $stepVersion */
                $stepVersion = $versionStep->stepVersion;

                if ($stepVersion === null) {
                    continue;
                }

                $position = $versionStep->position ?? ($index + 1);
                $reusedStep = $reusablePrefixSteps[$position] ?? null;

                $run->steps()->create([
                    'step_version_id' => $stepVersion->id,
                    'position' => $position,
                    'status' => $reusedStep === null ? 'pending' : 'done',
                    'start_time' => null,
                    'end_time' => null,
                    'error' => null,
                    'result' => $reusedStep?->result,
                    'input_tokens' => $reusedStep === null ? null : 0,
                    'output_tokens' => $reusedStep === null ? null : 0,
                    'cost' => $reusedStep === null ? null : 0,
                ]);
            }

            return $run;
        });

        if ($dispatchJob && $run->status === 'queued') {
            ProcessPipelineJob::dispatch($run->id)->onQueue(ProcessPipelineJob::QUEUE);
        }

        $run->load('pipelineVersion', 'lesson', 'steps.stepVersion.step');

        if (in_array($run->status, ['queued', 'running'], true)) {
            $this->eventBroadcaster->queueRunUpdated($run);
        } else {
            $this->eventBroadcaster->queueRunRemoved($run->id);
        }

        $this->eventBroadcaster->runUpdated($run);

        return $run;
    }

    private function resolveReusableSourceRun(Lesson $lesson, PipelineVersion $pipelineVersion): ?PipelineRun
    {
        return PipelineRun::query()
            ->where('lesson_id', $lesson->id)
            ->where('status', 'done')
            ->whereHas('pipelineVersion', function ($query) use ($pipelineVersion): void {
                $query->where('pipeline_id', $pipelineVersion->pipeline_id);
            })
            ->with(['steps' => fn ($query) => $query->orderBy('position')->orderBy('id')])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  Collection<int, PipelineVersionStep>  $versionSteps
     * @return array<int, PipelineRunStep>
     */
    private function resolveReusablePrefixSteps(Collection $versionSteps, ?PipelineRun $sourceRun): array
    {
        if ($sourceRun === null) {
            return [];
        }

        $sourceRun->loadMissing('steps');
        $sourceSteps = $sourceRun->steps->sortBy('position')->values();
        $reusableSteps = [];

        foreach ($versionSteps as $index => $versionStep) {
            /** @var StepVersion|null $stepVersion */
            $stepVersion = $versionStep->stepVersion;

            /** @var PipelineRunStep|null $sourceStep */
            $sourceStep = $sourceSteps->get($index);

            if ($stepVersion === null || $sourceStep === null) {
                break;
            }

            if ((int) $sourceStep->step_version_id !== (int) $stepVersion->id) {
                break;
            }

            if ($sourceStep->status !== 'done') {
                break;
            }

            $position = (int) ($versionStep->position ?? ($index + 1));
            $reusableSteps[$position] = $sourceStep;
        }

        return $reusableSteps;
    }

    /**
     * Сбрасывает состояние шага и всех следующих за ним и повторно ставит прогон в очередь.
     */
    public function restartFromStep(PipelineRun $run, PipelineRunStep $startingStep): PipelineRun
    {
        abort_if($startingStep->pipeline_run_id !== $run->id, 422, 'Шаг не принадлежит указанному прогону шаблона.');

        DB::transaction(function () use ($run, $startingStep): void {
            $run->steps()
                ->where('position', '>=', $startingStep->position)
                ->update([
                    'status' => 'pending',
                    'start_time' => null,
                    'end_time' => null,
                    'error' => null,
                    'result' => null,
                    'input_tokens' => null,
                    'output_tokens' => null,
                    'cost' => null,
                ]);

            $run->forceFill(['status' => 'queued'])->save();
        });

        $this->releasePendingRunLocks($run->id);

        ProcessPipelineJob::dispatch($run->id)->onQueue(ProcessPipelineJob::QUEUE);

        $run = $run->refresh()->load('pipelineVersion', 'lesson', 'steps.stepVersion.step');

        $this->eventBroadcaster->queueRunUpdated($run);
        $this->eventBroadcaster->runUpdated($run);

        return $run;
    }

    public function start(PipelineRun $run): PipelineRun
    {
        $shouldDispatch = false;

        DB::transaction(function () use ($run, &$shouldDispatch): void {
            /** @var PipelineRun $lockedRun */
            $lockedRun = PipelineRun::query()
                ->whereKey($run->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRun->status === 'done') {
                return;
            }

            if ($lockedRun->status === 'failed') {
                $failedStep = $lockedRun->steps()
                    ->where('status', 'failed')
                    ->orderBy('position')
                    ->first();

                if ($failedStep === null) {
                    return;
                }

                $lockedRun->steps()
                    ->where('position', '>=', $failedStep->position)
                    ->update([
                        'status' => 'pending',
                        'start_time' => null,
                        'end_time' => null,
                        'error' => null,
                        'result' => null,
                        'input_tokens' => null,
                        'output_tokens' => null,
                        'cost' => null,
                    ]);

                $state = $lockedRun->state ?? [];
                unset($state['stop_requested']);

                $lockedRun->forceFill([
                    'status' => 'queued',
                    'state' => $state,
                ])->save();

                $shouldDispatch = true;

                return;
            }

            $hasPausedSteps = $lockedRun->steps()
                ->where('status', 'paused')
                ->exists();

            if (! $hasPausedSteps) {
                return;
            }

            $lockedRun->steps()
                ->where('status', 'paused')
                ->update([
                    'status' => 'pending',
                    'start_time' => null,
                    'end_time' => null,
                    'error' => null,
                    'result' => null,
                    'input_tokens' => null,
                    'output_tokens' => null,
                    'cost' => null,
                ]);

            $state = $lockedRun->state ?? [];
            unset($state['stop_requested']);

            $hasRunningStep = $lockedRun->steps()
                ->where('status', 'running')
                ->exists();

            $lockedRun->forceFill([
                'status' => $hasRunningStep ? 'running' : 'queued',
                'state' => $state,
            ])->save();

            $shouldDispatch = ! $hasRunningStep;
        });

        if ($shouldDispatch) {
            $this->releasePendingRunLocks($run->id);
            ProcessPipelineJob::dispatch($run->id)->onQueue(ProcessPipelineJob::QUEUE);
        }

        $run = $run->refresh()->load('pipelineVersion', 'lesson', 'steps.stepVersion.step');

        if (in_array($run->status, ['queued', 'running'], true)) {
            $this->eventBroadcaster->queueRunUpdated($run);
        } else {
            $this->eventBroadcaster->queueRunRemoved($run->id);
        }

        $this->eventBroadcaster->runUpdated($run);

        return $run;
    }

    public function pause(PipelineRun $run): PipelineRun
    {
        DB::transaction(function () use ($run): void {
            /** @var PipelineRun $lockedRun */
            $lockedRun = PipelineRun::query()
                ->whereKey($run->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedRun->status, ['failed', 'done'], true)) {
                return;
            }

            $lockedRun->steps()
                ->where('status', 'pending')
                ->update(['status' => 'paused']);

            $state = $lockedRun->state ?? [];
            unset($state['stop_requested']);

            $hasRunningStep = $lockedRun->steps()
                ->where('status', 'running')
                ->exists();

            $lockedRun->forceFill([
                'status' => $hasRunningStep ? 'running' : 'paused',
                'state' => $state,
            ])->save();
        });

        $run = $run->refresh()->load('pipelineVersion', 'lesson', 'steps.stepVersion.step');

        if ($run->status === 'paused') {
            $this->eventBroadcaster->queueRunRemoved($run->id);
        } else {
            $this->eventBroadcaster->queueRunUpdated($run);
        }

        $this->eventBroadcaster->runUpdated($run);

        return $run;
    }

    public function stop(PipelineRun $run): PipelineRun
    {
        DB::transaction(function () use ($run): void {
            /** @var PipelineRun $lockedRun */
            $lockedRun = PipelineRun::query()
                ->whereKey($run->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedRun->status, ['failed', 'done'], true)) {
                return;
            }

            $lockedRun->steps()
                ->whereIn('status', ['pending', 'running'])
                ->update([
                    'status' => 'paused',
                    'start_time' => null,
                    'end_time' => null,
                    'error' => null,
                    'result' => null,
                    'input_tokens' => null,
                    'output_tokens' => null,
                    'cost' => null,
                ]);

            $state = $lockedRun->state ?? [];
            $state['stop_requested'] = true;

            $lockedRun->forceFill([
                'status' => 'paused',
                'state' => $state,
            ])->save();
        });

        $run = $run->refresh()->load('pipelineVersion', 'lesson', 'steps.stepVersion.step');

        $this->eventBroadcaster->queueRunRemoved($run->id);
        $this->eventBroadcaster->runUpdated($run);

        return $run;
    }

    private function releasePendingRunLocks(int $pipelineRunId): void
    {
        $cacheStore = Cache::store();
        $job = new ProcessPipelineJob($pipelineRunId);

        (new UniqueLock($cacheStore))->release($job);

        $overlapKey = 'laravel-queue-overlap:'.ProcessPipelineJob::class.':pipeline-run-'.$pipelineRunId;
        $cacheStore->lock($overlapKey)->forceRelease();
    }

    public function dispatchQueuedRuns(Lesson $lesson): void
    {
        $lesson->loadMissing('pipelineRuns');

        $lesson->pipelineRuns
            ->where('status', 'queued')
            ->each(function (PipelineRun $run): void {
                ProcessPipelineJob::dispatch($run->id)->onQueue(ProcessPipelineJob::QUEUE);
            });
    }

    public function setDownloadErrorOnFirstStep(Lesson $lesson, string $message): void
    {
        $errorMessage = trim($message);

        if ($errorMessage === '') {
            $errorMessage = 'Ошибка загрузки аудио.';
        }

        PipelineRun::query()
            ->where('lesson_id', $lesson->id)
            ->where('status', 'queued')
            ->with(['steps' => fn ($query) => $query
                ->orderBy('position')
                ->orderBy('id')])
            ->get()
            ->each(function (PipelineRun $run) use ($errorMessage): void {
                $firstStep = $run->steps->first();

                if ($firstStep === null) {
                    return;
                }

                $firstStep->forceFill([
                    'error' => $errorMessage,
                ])->save();
            });
    }
}
