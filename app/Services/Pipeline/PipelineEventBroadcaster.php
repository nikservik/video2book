<?php

namespace App\Services\Pipeline;

use App\Models\PipelineQueueEvent;
use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Support\PipelineRunTransformer;

final class PipelineEventBroadcaster
{
    public const QUEUE_STREAM = 'queue';
    private const RUN_STREAM_PREFIX = 'run:';

    public function queueRunUpdated(PipelineRun $run): void
    {
        $this->publish(
            self::QUEUE_STREAM,
            'queue-run-updated',
            [
                'run' => PipelineRunTransformer::run($run, includeResults: false),
            ]
        );
    }

    public function queueRunRemoved(int $runId): void
    {
        $this->publish(
            self::QUEUE_STREAM,
            'queue-run-removed',
            ['id' => $runId]
        );
    }

    public function runUpdated(PipelineRun $run): void
    {
        $this->publish(
            $this->runStream($run->id),
            'run-updated',
            [
                'run' => PipelineRunTransformer::run($run),
            ]
        );
    }

    public function stepUpdated(PipelineRunStep $step): void
    {
        $payload = [
            'run_id' => $step->pipeline_run_id,
            'step' => PipelineRunTransformer::step($step),
        ];

        $this->publish(
            $this->runStream($step->pipeline_run_id),
            'run-step-updated',
            $payload
        );
    }

    private function publish(string $stream, string $event, array $payload): void
    {
        PipelineQueueEvent::query()->create([
            'stream' => $stream,
            'event' => $event,
            'payload' => $payload,
        ]);
    }

    private function runStream(int $runId): string
    {
        return self::RUN_STREAM_PREFIX.$runId;
    }
}
