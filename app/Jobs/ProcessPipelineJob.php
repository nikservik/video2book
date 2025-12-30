<?php

namespace App\Jobs;

use App\Services\Pipeline\PipelineRunProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ProcessPipelineJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const QUEUE = 'pipelines';

    public int $timeout = 1800; // секунды

    public int $uniqueFor = 3600; // секунды

    public function __construct(public readonly int $pipelineRunId)
    {
        $this->onQueue(self::QUEUE);
    }

    /**
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("pipeline-run-{$this->pipelineRunId}"))
                ->expireAfter(3600),
        ];
    }

    public function uniqueId(): string
    {
        return "pipeline-run:{$this->pipelineRunId}";
    }

    public function handle(PipelineRunProcessingService $service): void
    {
        $hasPending = $service->handle($this->pipelineRunId);

        if ($hasPending) {
            self::dispatch($this->pipelineRunId)->onQueue(self::QUEUE);
        }
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'pipeline-run:'.$this->pipelineRunId,
        ];
    }
}
