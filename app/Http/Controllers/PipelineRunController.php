<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\PipelineQueueEvent;
use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\PipelineVersion;
use App\Services\Pipeline\PipelineEventBroadcaster;
use App\Services\Pipeline\PipelineRunService;
use App\Support\LessonDownloadTransformer;
use App\Support\PipelineRunTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PipelineRunController extends Controller
{
    public function __construct(
        private readonly PipelineRunService $pipelineRunService,
        private readonly PipelineEventBroadcaster $eventBroadcaster,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lesson_id' => ['required', 'exists:lessons,id'],
            'pipeline_version_id' => ['required', 'exists:pipeline_versions,id'],
        ]);

        $lesson = Lesson::query()->findOrFail($data['lesson_id']);
        $pipelineVersion = PipelineVersion::query()->findOrFail($data['pipeline_version_id']);

        $run = $this->pipelineRunService
            ->createRun($lesson, $pipelineVersion)
            ->loadMissing('steps.stepVersion.step', 'pipelineVersion', 'lesson.project');

        return response()->json([
            'data' => PipelineRunTransformer::run($run),
        ], 201);
    }

    public function queue(): JsonResponse
    {
        $runs = $this->queuedRunsQuery()
            ->get()
            ->map(fn (PipelineRun $run) => PipelineRunTransformer::run($run, includeResults: false));

        return response()->json(['data' => $runs]);
    }

    public function queueEvents(): StreamedResponse
    {
        $runs = $this->queuedRunsQuery()
            ->get()
            ->map(fn (PipelineRun $run) => PipelineRunTransformer::run($run, includeResults: false))
            ->values()
            ->all();
        $downloads = $this->downloadQueueSnapshot();

        return response()->stream(function () use ($runs, $downloads): void {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            @ob_implicit_flush(true);
            set_time_limit(0);

            $this->sendEvent('queue-snapshot', [
                'runs' => $runs,
                'downloads' => $downloads,
            ]);

            $stream = PipelineEventBroadcaster::QUEUE_STREAM;
            $lastId = 0;
            $lastKeepAlive = now();

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                $messages = $this->readEvents($stream, $lastId);

                if ($messages === null || count($messages) === 0) {
                    if ($lastKeepAlive->diffInSeconds(now()) >= 20) {
                        $this->sendComment('keepalive');
                        $lastKeepAlive = now();
                    }
                    usleep(500000);
                    continue;
                }

                foreach ($messages as $messageId => $payload) {
                    $lastId = $messageId;
                    $eventName = $payload['event'] ?? 'queue-update';
                    $data = $payload['payload'] ?? [];
                    $this->sendEvent($eventName, $data);
                }

                PipelineQueueEvent::query()
                    ->where('stream', $stream)
                    ->where('id', '<', max(0, $lastId - PipelineEventBroadcaster::STREAM_EVENT_LIMIT))
                    ->delete();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function runEvents(Request $request, PipelineRun $pipelineRun): StreamedResponse
    {
        $pipelineRun->load('lesson.project', 'pipelineVersion', 'steps.stepVersion.step');

        $runSnapshot = PipelineRunTransformer::run($pipelineRun, includeResults: true);
        $stream = PipelineEventBroadcaster::runStreamName($pipelineRun->id);
        $singleShot = $request->boolean('once');

        return response()->stream(function () use ($runSnapshot, $stream, $singleShot): void {
            if (! $singleShot) {
                while (ob_get_level() > 0) {
                    ob_end_flush();
                }

                @ob_implicit_flush(true);
                set_time_limit(0);
            }

            $this->sendEvent('run-snapshot', ['run' => $runSnapshot]);

            if ($singleShot) {
                return;
            }

            $lastId = 0;
            $lastKeepAlive = now();

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                $messages = $this->readEvents($stream, $lastId);

                if ($messages === null || count($messages) === 0) {
                    if ($lastKeepAlive->diffInSeconds(now()) >= 20) {
                        $this->sendComment('keepalive');
                        $lastKeepAlive = now();
                    }
                    usleep(500000);
                    continue;
                }

                foreach ($messages as $messageId => $payload) {
                    $lastId = $messageId;
                    $eventName = $payload['event'] ?? 'run-updated';
                    $data = $payload['payload'] ?? [];
                    $this->sendEvent($eventName, $data);
                }

                PipelineQueueEvent::query()
                    ->where('stream', $stream)
                    ->where('id', '<', max(0, $lastId - PipelineEventBroadcaster::STREAM_EVENT_LIMIT))
                    ->delete();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function show(PipelineRun $pipelineRun): JsonResponse
    {
        $pipelineRun->load('lesson.project', 'pipelineVersion', 'steps.stepVersion.step');

        return response()->json([
            'data' => PipelineRunTransformer::run($pipelineRun, includeResults: true),
        ]);
    }

    public function restart(Request $request, PipelineRun $pipelineRun): JsonResponse
    {
        $data = $request->validate([
            'step_id' => ['required', 'exists:pipeline_run_steps,id'],
        ]);

        $step = PipelineRunStep::query()->findOrFail($data['step_id']);

        $run = $this->pipelineRunService
            ->restartFromStep($pipelineRun, $step)
            ->loadMissing('steps.stepVersion.step', 'pipelineVersion', 'lesson.project');

        return response()->json(['data' => PipelineRunTransformer::run($run, includeResults: false)]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readEvents(string $stream, int $lastId): ?array
    {
        $events = PipelineQueueEvent::query()
            ->where('stream', $stream)
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->limit(100)
            ->get();

        if ($events->isEmpty()) {
            return null;
        }

        $messages = [];

        foreach ($events as $event) {
            $messages[$event->id] = [
                'event' => $event->event,
                'payload' => $event->payload ?? [],
            ];
        }

        return $messages;
    }

    private function queuedRunsQuery()
    {
        return PipelineRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->with(['lesson.project', 'pipelineVersion', 'steps.stepVersion.step'])
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function downloadQueueSnapshot(): array
    {
        return Lesson::query()
            ->where('settings->downloading', true)
            ->with('project')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Lesson $lesson) => LessonDownloadTransformer::task($lesson))
            ->values()
            ->all();
    }

    private function sendEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";

        if (function_exists('flush')) {
            flush();
        }
    }

    private function sendComment(string $comment): void
    {
        echo ": {$comment}\n\n";

        if (function_exists('flush')) {
            flush();
        }
    }
}
