<?php

namespace App\Services\Pipeline;

use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Support\PipelineRunTransformer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class PipelineEventBroadcaster
{
    public const QUEUE_STREAM = 'pipeline:queue-events';
    private const RUN_STREAM_PREFIX = 'pipeline:run-events:';
    private const MAX_STREAM_LENGTH = 1000;

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
        try {
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);

            if ($encoded === false) {
                return;
            }

            $redis = Redis::connection();
            $redis->xAdd($stream, '*', [
                'event' => $event,
                'payload' => $encoded,
            ]);

            $redis->xTrim($stream, self::MAX_STREAM_LENGTH, true);
        } catch (Throwable $throwable) {
            Log::warning('Failed to publish pipeline event', [
                'stream' => $stream,
                'event' => $event,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    private function runStream(int $runId): string
    {
        return self::RUN_STREAM_PREFIX.$runId;
    }
}
