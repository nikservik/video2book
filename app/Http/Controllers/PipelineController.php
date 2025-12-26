<?php

namespace App\Http\Controllers;

use App\Http\Resources\StepVersionResource;
use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Step;
use App\Models\StepVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PipelineController extends Controller
{
    public function index(): JsonResponse
    {
        $pipelines = Pipeline::query()
            ->whereHas('currentVersion', fn ($query) => $query->where('status', 'active'))
            ->with(['currentVersion.versionSteps.stepVersion.step'])
            ->get()
            ->map(fn (Pipeline $pipeline) => $this->transformPipeline($pipeline));

        return response()->json(['data' => $pipelines]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'changelog' => ['nullable', 'string'],
            'created_by' => ['nullable', 'exists:users,id'],
            'status' => ['nullable', 'in:active,archived'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*' => ['required', 'string', 'max:255'],
        ]);

        $pipeline = DB::transaction(function () use ($data): Pipeline {
            $pipeline = Pipeline::query()->create();
            $version = $pipeline->versions()->create([
                'version' => 1,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'changelog' => $data['changelog'] ?? null,
                'created_by' => $data['created_by'] ?? null,
                'status' => $data['status'] ?? 'active',
            ]);

            $pipeline->update(['current_version_id' => $version->id]);

            foreach ($data['steps'] as $name) {
                $pipeline->steps()->create(['name' => $name]);
            }

            return $pipeline->load(['currentVersion.versionSteps.stepVersion.step', 'steps']);
        });

        return response()->json(['data' => $this->transformPipeline($pipeline)], 201);
    }

    public function createInitialStepVersion(Request $request, Pipeline $pipeline, Step $step): JsonResponse
    {
        $this->assertStepBelongsToPipeline($pipeline, $step);

        $data = $request->validate([
            'type' => ['required', 'in:transcribe,text,glossary'],
            'description' => ['nullable', 'string'],
            'prompt' => ['nullable', 'string'],
            'settings' => ['required', 'array'],
            'status' => ['nullable', 'in:active,disabled'],
        ]);

        abort_if($step->current_version_id !== null, 422, 'Step already has versions.');

        $pipelineVersion = $this->requireCurrentVersion($pipeline);

        $stepVersion = DB::transaction(function () use ($pipeline, $pipelineVersion, $step, $data): StepVersion {
            $stepVersion = $step->versions()->create([
                'type' => $data['type'],
                'version' => 1,
                'description' => $data['description'] ?? null,
                'prompt' => $data['prompt'] ?? null,
                'settings' => $data['settings'],
                'status' => $data['status'] ?? 'active',
            ]);

            $step->update(['current_version_id' => $stepVersion->id]);

            $this->syncInitialPipelineVersionSteps($pipeline, $pipelineVersion);

            return $stepVersion;
        });

        return response()->json([
            'data' => StepVersionResource::make($stepVersion),
        ], 201);
    }

    public function addStep(Request $request, Pipeline $pipeline): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:transcribe,text,glossary'],
            'description' => ['nullable', 'string'],
            'prompt' => ['nullable', 'string'],
            'settings' => ['required', 'array'],
            'status' => ['nullable', 'in:active,disabled'],
            'changelog_entry' => ['required', 'string'],
            'created_by' => ['nullable', 'exists:users,id'],
        ]);

        $previousVersion = $this->requireCurrentVersion($pipeline);

        $pipeline = DB::transaction(function () use ($pipeline, $previousVersion, $data): Pipeline {
            $step = $pipeline->steps()->create(['name' => $data['name']]);
            $stepVersion = $step->versions()->create([
                'type' => $data['type'],
                'version' => 1,
                'description' => $data['description'] ?? null,
                'prompt' => $data['prompt'] ?? null,
                'settings' => $data['settings'],
                'status' => $data['status'] ?? 'active',
            ]);
            $step->update(['current_version_id' => $stepVersion->id]);

            $stepsPayload = $this->collectVersionSteps($previousVersion);
            $stepsPayload[] = [
                'step_id' => $step->id,
                'step_version_id' => $stepVersion->id,
            ];

            $this->createPipelineVersionFromPrevious(
                $pipeline,
                $previousVersion,
                $data['changelog_entry'],
                $data['created_by'] ?? null,
                $stepsPayload,
            );

            return $pipeline->load(['currentVersion.versionSteps.stepVersion.step', 'steps']);
        });

        return response()->json(['data' => $this->transformPipeline($pipeline)], 201);
    }

    public function updateStep(Request $request, Pipeline $pipeline, Step $step): JsonResponse
    {
        $this->assertStepBelongsToPipeline($pipeline, $step);

        $data = $request->validate([
            'type' => ['required', 'in:transcribe,text,glossary'],
            'description' => ['nullable', 'string'],
            'prompt' => ['nullable', 'string'],
            'settings' => ['required', 'array'],
            'status' => ['nullable', 'in:active,disabled'],
            'changelog_entry' => ['required', 'string'],
            'created_by' => ['nullable', 'exists:users,id'],
        ]);

        $previousVersion = $this->requireCurrentVersion($pipeline);

        $pipeline = DB::transaction(function () use ($pipeline, $previousVersion, $step, $data): Pipeline {
            $nextVersionNumber = ($step->versions()->max('version') ?? 0) + 1;
            $stepVersion = $step->versions()->create([
                'type' => $data['type'],
                'version' => $nextVersionNumber,
                'description' => $data['description'] ?? null,
                'prompt' => $data['prompt'] ?? null,
                'settings' => $data['settings'],
                'status' => $data['status'] ?? 'active',
            ]);
            $step->update(['current_version_id' => $stepVersion->id]);

            $stepsPayload = collect($this->collectVersionSteps($previousVersion))
                ->map(function (array $item) use ($step, $stepVersion): array {
                    if ($item['step_id'] === $step->id) {
                        $item['step_version_id'] = $stepVersion->id;
                    }

                    return $item;
                })
                ->all();

            $this->createPipelineVersionFromPrevious(
                $pipeline,
                $previousVersion,
                $data['changelog_entry'],
                $data['created_by'] ?? null,
                $stepsPayload,
            );

            return $pipeline->load(['currentVersion.versionSteps.stepVersion.step', 'steps']);
        });

        return response()->json(['data' => $this->transformPipeline($pipeline)]);
    }

    public function removeStep(Request $request, Pipeline $pipeline, Step $step): JsonResponse
    {
        $this->assertStepBelongsToPipeline($pipeline, $step);

        $data = $request->validate([
            'changelog_entry' => ['required', 'string'],
            'created_by' => ['nullable', 'exists:users,id'],
        ]);

        $previousVersion = $this->requireCurrentVersion($pipeline);

        $stepsPayload = array_values(array_filter(
            $this->collectVersionSteps($previousVersion),
            fn (array $item) => $item['step_id'] !== $step->id
        ));

        $pipeline = DB::transaction(function () use ($pipeline, $previousVersion, $data, $stepsPayload): Pipeline {
            $this->createPipelineVersionFromPrevious(
                $pipeline,
                $previousVersion,
                $data['changelog_entry'],
                $data['created_by'] ?? null,
                $stepsPayload,
            );

            return $pipeline->load(['currentVersion.versionSteps.stepVersion.step', 'steps']);
        });

        return response()->json(['data' => $this->transformPipeline($pipeline)]);
    }

    public function update(Request $request, Pipeline $pipeline): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'changelog_entry' => ['required', 'string'],
            'created_by' => ['nullable', 'exists:users,id'],
        ]);

        $previousVersion = $this->requireCurrentVersion($pipeline);

        $pipeline = DB::transaction(function () use ($pipeline, $previousVersion, $data): Pipeline {
            $this->createPipelineVersionFromPrevious(
                $pipeline,
                $previousVersion,
                $data['changelog_entry'],
                $data['created_by'] ?? null,
                collect($this->collectVersionSteps($previousVersion))->all(),
                $data['title'],
                $data['description'] ?? null,
            );

            return $pipeline->load(['currentVersion.versionSteps.stepVersion.step', 'steps']);
        });

        return response()->json(['data' => $this->transformPipeline($pipeline)]);
    }

    public function archive(Pipeline $pipeline): JsonResponse
    {
        $version = $this->requireCurrentVersion($pipeline);
        $version->update(['status' => 'archived']);

        return response()->json(['data' => $this->transformPipeline($pipeline->load('currentVersion.versionSteps.stepVersion.step'))]);
    }

    public function versions(Pipeline $pipeline): JsonResponse
    {
        $versions = $pipeline->versions()
            ->with('versionSteps.stepVersion.step')
            ->orderBy('version')
            ->get()
            ->map(fn (PipelineVersion $version) => $this->transformPipelineVersion($version));

        return response()->json(['data' => $versions]);
    }

    public function pipelineVersionSteps(PipelineVersion $pipelineVersion): JsonResponse
    {
        $pipelineVersion->load('versionSteps.stepVersion.step');

        $steps = $pipelineVersion->versionSteps
            ->sortBy('position')
            ->values()
            ->map(function (PipelineVersionStep $versionStep): array {
                $stepVersion = $versionStep->stepVersion;

                return [
                    'position' => $versionStep->position,
                    'step' => [
                        'id' => $stepVersion->step->id,
                        'name' => $stepVersion->step->name,
                    ],
                    'version' => StepVersionResource::make($stepVersion)->toArray(request()),
                ];
            });

        return response()->json(['data' => $steps]);
    }

    private function assertStepBelongsToPipeline(Pipeline $pipeline, Step $step): void
    {
        abort_if($step->pipeline_id !== $pipeline->id, 404, 'Step does not belong to the pipeline.');
    }

    private function requireCurrentVersion(Pipeline $pipeline): PipelineVersion
    {
        $pipeline->loadMissing('currentVersion.versionSteps.stepVersion.step');
        $version = $pipeline->currentVersion;

        abort_if($version === null, 422, 'Pipeline has no current version.');

        return $version;
    }

    /**
     * @return array<int, array{step_id:int, step_version_id:int}>
     */
    private function collectVersionSteps(PipelineVersion $version): array
    {
        $version->loadMissing('versionSteps.stepVersion.step');

        return $version->versionSteps
            ->sortBy('position')
            ->map(fn (PipelineVersionStep $item): array => [
                'step_id' => $item->stepVersion->step_id,
                'step_version_id' => $item->step_version_id,
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<int, array{step_id:int, step_version_id:int}>|null $stepPayload
     */
    private function createPipelineVersionFromPrevious(
        Pipeline $pipeline,
        PipelineVersion $previousVersion,
        string $changelogEntry,
        ?int $createdBy = null,
        ?array $stepPayload = null,
        ?string $title = null,
        ?string $description = null,
    ): PipelineVersion {
        $newVersion = $pipeline->versions()->create([
            'version' => $previousVersion->version + 1,
            'title' => $title ?? $previousVersion->title,
            'description' => $description ?? $previousVersion->description,
            'changelog' => $this->composeChangelog($previousVersion->changelog, $changelogEntry),
            'created_by' => $createdBy ?? $previousVersion->created_by,
            'status' => $previousVersion->status,
        ]);

        $steps = $stepPayload ?? $this->collectVersionSteps($previousVersion);

        foreach ($steps as $index => $stepData) {
            PipelineVersionStep::create([
                'pipeline_version_id' => $newVersion->id,
                'step_version_id' => $stepData['step_version_id'],
                'position' => $index + 1,
            ]);
        }

        $pipeline->update(['current_version_id' => $newVersion->id]);

        return $newVersion;
    }

    private function composeChangelog(?string $existing, string $entry): string
    {
        return collect([$existing, $entry])
            ->filter(fn (?string $value) => !empty(trim((string) $value)))
            ->implode("\n");
    }

    private function syncInitialPipelineVersionSteps(Pipeline $pipeline, PipelineVersion $pipelineVersion): void
    {
        $steps = $pipeline->steps()->orderBy('id')->get();

        PipelineVersionStep::where('pipeline_version_id', $pipelineVersion->id)->delete();

        $position = 1;
        foreach ($steps as $step) {
            if ($step->current_version_id === null) {
                continue;
            }

            PipelineVersionStep::create([
                'pipeline_version_id' => $pipelineVersion->id,
                'step_version_id' => $step->current_version_id,
                'position' => $position++,
            ]);
        }
    }

    private function transformPipeline(Pipeline $pipeline): array
    {
        $pipeline->loadMissing('currentVersion.versionSteps.stepVersion.step');

        return [
            'id' => $pipeline->id,
            'current_version' => $pipeline->currentVersion
                ? $this->transformPipelineVersion($pipeline->currentVersion)
                : null,
            'steps' => $pipeline->steps()
                ->orderBy('id')
                ->get()
                ->map(fn (Step $step) => [
                    'id' => $step->id,
                    'name' => $step->name,
                    'current_version_id' => $step->current_version_id,
                ])
                ->values()
                ->all(),
        ];
    }

    private function transformPipelineVersion(PipelineVersion $version): array
    {
        $version->loadMissing('versionSteps.stepVersion.step');

        return [
            'id' => $version->id,
            'pipeline_id' => $version->pipeline_id,
            'version' => $version->version,
            'title' => $version->title,
            'description' => $version->description,
            'changelog' => $version->changelog,
            'created_by' => $version->created_by,
            'status' => $version->status,
            'steps' => $version->versionSteps
                ->sortBy('position')
                ->values()
                ->map(function (PipelineVersionStep $versionStep): array {
                    $stepVersion = $versionStep->stepVersion;

                    return [
                        'position' => $versionStep->position,
                        'step' => [
                            'id' => $stepVersion->step->id,
                            'name' => $stepVersion->step->name,
                        ],
                        'version' => StepVersionResource::make($stepVersion)->toArray(request()),
                    ];
                })
                ->all(),
        ];
    }
}
