<?php

namespace App\Http\Controllers;

use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\PipelineVersion;
use App\Models\Project;
use App\Services\Pipeline\PipelineRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineRunController extends Controller
{
    public function __construct(private readonly PipelineRunService $pipelineRunService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'pipeline_version_id' => ['required', 'exists:pipeline_versions,id'],
        ]);

        $project = Project::query()->findOrFail($data['project_id']);
        $pipelineVersion = PipelineVersion::query()->findOrFail($data['pipeline_version_id']);

        $run = $this->pipelineRunService
            ->createRun($project, $pipelineVersion)
            ->loadMissing('steps.stepVersion.step', 'pipelineVersion', 'project');

        return response()->json([
            'data' => $this->transformRun($run),
        ], 201);
    }

    public function queue(): JsonResponse
    {
        $runs = PipelineRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->with(['project', 'pipelineVersion', 'steps.stepVersion.step'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (PipelineRun $run) => $this->transformRun($run));

        return response()->json(['data' => $runs]);
    }

    public function restart(Request $request, PipelineRun $pipelineRun): JsonResponse
    {
        $data = $request->validate([
            'step_id' => ['required', 'exists:pipeline_run_steps,id'],
        ]);

        $step = PipelineRunStep::query()->findOrFail($data['step_id']);

        $run = $this->pipelineRunService
            ->restartFromStep($pipelineRun, $step)
            ->loadMissing('steps.stepVersion.step', 'pipelineVersion', 'project');

        return response()->json(['data' => $this->transformRun($run)]);
    }

    private function transformRun(PipelineRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'state' => $run->state ?? [],
            'project' => $run->project
                ? [
                    'id' => $run->project->id,
                    'name' => $run->project->name,
                ]
                : null,
            'pipeline_version' => $run->pipelineVersion
                ? [
                    'id' => $run->pipelineVersion->id,
                    'title' => $run->pipelineVersion->title,
                    'version' => $run->pipelineVersion->version,
                ]
                : null,
            'steps' => $run->steps
                ->sortBy('position')
                ->values()
                ->map(function (PipelineRunStep $step): array {
                    $stepVersion = $step->stepVersion;

                    return [
                        'id' => $step->id,
                        'position' => $step->position,
                        'status' => $step->status,
                        'name' => $stepVersion?->name,
                        'type' => $stepVersion?->type,
                        'start_time' => optional($step->start_time)->toISOString(),
                        'end_time' => optional($step->end_time)->toISOString(),
                        'input_tokens' => $step->input_tokens,
                        'output_tokens' => $step->output_tokens,
                        'cost' => $step->cost,
                    ];
                })
                ->all(),
            'created_at' => optional($run->created_at)->toISOString(),
            'updated_at' => optional($run->updated_at)->toISOString(),
        ];
    }
}
