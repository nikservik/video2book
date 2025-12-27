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
use Illuminate\Validation\Rule;

class PipelineController extends Controller
{
    public function index(): JsonResponse
    {
        $pipelines = Pipeline::query()
            ->whereHas('currentVersion', fn ($query) => $query->where('status', 'active'))
            ->with([
                'currentVersion.versionSteps.stepVersion.step',
                'currentVersion.versionSteps.stepVersion.inputStep.currentVersion',
                'steps.currentVersion',
            ])
            ->get()
            ->map(fn (Pipeline $pipeline) => $this->transformPipeline($pipeline));

        return response()->json(['data' => $pipelines]);
    }

    public function show(Pipeline $pipeline): JsonResponse
    {
        $pipeline->load([
            'currentVersion.versionSteps.stepVersion.step',
            'currentVersion.versionSteps.stepVersion.inputStep.currentVersion',
            'steps.currentVersion',
        ]);

        return response()->json(['data' => $this->transformPipeline($pipeline)]);
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

            $previousStep = null;
            foreach ($data['steps'] as $index => $name) {
                $step = $pipeline->steps()->create();

                $stepVersion = $step->versions()->create([
                    'name' => $name,
                    'type' => $index === 0 ? 'transcribe' : 'text',
                    'version' => 1,
                    'description' => null,
                    'prompt' => null,
                    'settings' => [],
                    'status' => 'draft',
                    'input_step_id' => $previousStep?->id,
                ]);

                $step->update(['current_version_id' => $stepVersion->id]);
                $previousStep = $step;
            }

            $this->syncInitialPipelineVersionSteps($pipeline, $version);

            return $pipeline->load([
                'currentVersion.versionSteps.stepVersion.step',
                'currentVersion.versionSteps.stepVersion.inputStep.currentVersion',
                'steps.currentVersion',
            ]);
        });

        return response()->json(['data' => $this->transformPipeline($pipeline)], 201);
    }

    public function createInitialStepVersion(Request $request, Pipeline $pipeline, Step $step): JsonResponse
    {
        $this->assertStepBelongsToPipeline($pipeline, $step);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:transcribe,text,glossary'],
            'description' => ['nullable', 'string'],
            'prompt' => ['nullable', 'string'],
            'settings' => ['required', 'array'],
            'status' => ['nullable', 'in:draft,active,disabled'],
            'input_step_id' => ['nullable', 'exists:steps,id'],
        ]);

        abort_if($step->current_version_id !== null, 422, 'Step already has versions.');
        $inputStep = $this->resolveInputStep($pipeline, $step, $data['input_step_id'] ?? null);

        $pipelineVersion = $this->requireCurrentVersion($pipeline);

        $stepVersion = DB::transaction(function () use ($pipeline, $pipelineVersion, $step, $data, $inputStep): StepVersion {
            $stepVersion = $step->versions()->create([
                'name' => $data['name'],
                'type' => $data['type'],
                'version' => 1,
                'description' => $data['description'] ?? null,
                'prompt' => $data['prompt'] ?? null,
                'settings' => $data['settings'],
                'status' => $data['status'] ?? 'active',
                'input_step_id' => $inputStep?->id,
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
            'status' => ['nullable', 'in:draft,active,disabled'],
            'changelog_entry' => ['required', 'string'],
            'created_by' => ['nullable', 'exists:users,id'],
            'position' => ['nullable', 'integer', 'min:1'],
            'input_step_id' => ['nullable', 'exists:steps,id'],
        ]);

        $previousVersion = $this->requireCurrentVersion($pipeline);
        $inputStep = $this->resolveInputStep($pipeline, null, $data['input_step_id'] ?? null);

        $pipeline = DB::transaction(function () use ($pipeline, $previousVersion, $data, $inputStep): Pipeline {
            $step = $pipeline->steps()->create();
            $stepVersion = $step->versions()->create([
                'name' => $data['name'],
                'type' => $data['type'],
                'version' => 1,
                'description' => $data['description'] ?? null,
                'prompt' => $data['prompt'] ?? null,
                'settings' => $data['settings'],
                'status' => $data['status'] ?? 'active',
                'input_step_id' => $inputStep?->id,
            ]);
            $step->update(['current_version_id' => $stepVersion->id]);

            $stepsPayload = $this->collectVersionSteps($previousVersion);
            $position = $data['position'] ?? count($stepsPayload) + 1;
            $position = max(1, min($position, count($stepsPayload) + 1));

            array_splice($stepsPayload, $position - 1, 0, [[
                'step_id' => $step->id,
                'step_version_id' => $stepVersion->id,
            ]]);

            $this->createPipelineVersionFromPrevious(
                $pipeline,
                $previousVersion,
                changelogEntry: $data['changelog_entry'],
                createdBy: $data['created_by'] ?? null,
                stepPayload: $stepsPayload,
            );

            return $pipeline->load([
                'currentVersion.versionSteps.stepVersion.step',
                'currentVersion.versionSteps.stepVersion.inputStep.currentVersion',
                'steps.currentVersion',
            ]);
        });

        return response()->json(['data' => $this->transformPipeline($pipeline)], 201);
    }

    public function updateStep(Request $request, Pipeline $pipeline, Step $step): JsonResponse
    {
        $this->assertStepBelongsToPipeline($pipeline, $step);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:transcribe,text,glossary'],
            'description' => ['nullable', 'string'],
            'prompt' => ['nullable', 'string'],
            'settings' => ['required', 'array'],
            'status' => ['nullable', 'in:draft,active,disabled'],
            'changelog_entry' => ['nullable', 'string'],
            'created_by' => ['nullable', 'exists:users,id'],
            'input_step_id' => ['nullable', 'exists:steps,id'],
            'mode' => ['required', Rule::in(['current', 'new_version'])],
        ]);

        $previousVersion = $this->requireCurrentVersion($pipeline);
        $inputStep = $this->resolveInputStep($pipeline, $step, $data['input_step_id'] ?? null);

        if ($data['mode'] === 'current') {
            $currentVersion = $step->currentVersion;
            abort_if($currentVersion === null, 422, 'Step has no current version.');

            $currentVersion->update([
                'name' => $data['name'],
                'type' => $data['type'],
                'description' => $data['description'] ?? null,
                'prompt' => $data['prompt'] ?? null,
                'settings' => $data['settings'],
                'status' => $data['status'] ?? $currentVersion->status,
                'input_step_id' => $inputStep?->id,
            ]);

            $pipeline->load([
                'currentVersion.versionSteps.stepVersion.step',
                'currentVersion.versionSteps.stepVersion.inputStep.currentVersion',
                'steps.currentVersion',
            ]);

            return response()->json(['data' => $this->transformPipeline($pipeline)]);
        }

        abort_if(empty($data['changelog_entry']), 422, 'Описание изменения обязательно для новой версии шага.');

        $pipeline = DB::transaction(function () use ($pipeline, $previousVersion, $step, $data, $inputStep): Pipeline {
            $nextVersionNumber = ($step->versions()->max('version') ?? 0) + 1;
            $stepVersion = $step->versions()->create([
                'name' => $data['name'],
                'type' => $data['type'],
                'version' => $nextVersionNumber,
                'description' => $data['description'] ?? null,
                'prompt' => $data['prompt'] ?? null,
                'settings' => $data['settings'],
                'status' => $data['status'] ?? 'active',
                'input_step_id' => $inputStep?->id,
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
                changelogEntry: $data['changelog_entry'],
                createdBy: $data['created_by'] ?? null,
                stepPayload: $stepsPayload,
            );

            return $pipeline->load([
                'currentVersion.versionSteps.stepVersion.step',
                'currentVersion.versionSteps.stepVersion.inputStep.currentVersion',
                'steps.currentVersion',
            ]);
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
                changelogEntry: $data['changelog_entry'],
                createdBy: $data['created_by'] ?? null,
                stepPayload: $stepsPayload,
            );

            return $pipeline->load([
                'currentVersion.versionSteps.stepVersion.step',
                'currentVersion.versionSteps.stepVersion.inputStep.currentVersion',
                'steps.currentVersion',
            ]);
        });

        return response()->json(['data' => $this->transformPipeline($pipeline)]);
    }

    public function update(Request $request, Pipeline $pipeline): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'created_by' => ['nullable', 'exists:users,id'],
            'changelog_entry' => ['nullable', 'string'],
            'mode' => ['required', Rule::in(['current', 'new_version'])],
        ]);

        $previousVersion = $this->requireCurrentVersion($pipeline);

        if ($data['mode'] === 'current') {
            $previousVersion->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
            ]);

            $pipeline->load([
                'currentVersion.versionSteps.stepVersion.step',
                'currentVersion.versionSteps.stepVersion.inputStep.currentVersion',
                'steps.currentVersion',
            ]);

            return response()->json(['data' => $this->transformPipeline($pipeline)]);
        }

        $pipeline = DB::transaction(function () use ($pipeline, $previousVersion, $data): Pipeline {
            $this->createPipelineVersionFromPrevious(
                $pipeline,
                $previousVersion,
                changelogEntry: $data['changelog_entry'] ?? 'Обновлены название и описание пайплайна',
                createdBy: $data['created_by'] ?? null,
                stepPayload: collect($this->collectVersionSteps($previousVersion))->all(),
                title: $data['title'],
                description: $data['description'] ?? null,
            );

            return $pipeline->load([
                'currentVersion.versionSteps.stepVersion.step',
                'currentVersion.versionSteps.stepVersion.inputStep.currentVersion',
                'steps.currentVersion',
            ]);
        });

        return response()->json(['data' => $this->transformPipeline($pipeline)]);
    }

    public function archive(Pipeline $pipeline): JsonResponse
    {
        $version = $this->requireCurrentVersion($pipeline);
        $version->update(['status' => 'archived']);

        return response()->json([
            'data' => $this->transformPipeline(
                $pipeline->load(
                    'currentVersion.versionSteps.stepVersion.step',
                    'currentVersion.versionSteps.stepVersion.inputStep.currentVersion',
                )
            ),
        ]);
    }

    public function versions(Pipeline $pipeline): JsonResponse
    {
        $versions = $pipeline->versions()
            ->with('versionSteps.stepVersion.step', 'versionSteps.stepVersion.inputStep.currentVersion')
            ->orderBy('version')
            ->get()
            ->map(fn (PipelineVersion $version) => $this->transformPipelineVersion($version));

        return response()->json(['data' => $versions]);
    }

    public function pipelineVersionSteps(PipelineVersion $pipelineVersion): JsonResponse
    {
        $pipelineVersion->load('versionSteps.stepVersion.step', 'versionSteps.stepVersion.inputStep.currentVersion');

        $steps = $pipelineVersion->versionSteps
            ->sortBy('position')
            ->values()
            ->map(function (PipelineVersionStep $versionStep): array {
                $stepVersion = $versionStep->stepVersion;

                return [
                    'position' => $versionStep->position,
                    'step' => [
                        'id' => $stepVersion->step->id,
                        'name' => $stepVersion->name,
                    ],
                    'version' => StepVersionResource::make($stepVersion)->toArray(request()),
                    'input_step' => $stepVersion->inputStep
                        ? [
                            'id' => $stepVersion->inputStep->id,
                            'name' => $stepVersion->inputStep->currentVersion?->name,
                        ]
                        : null,
                ];
            });

        return response()->json(['data' => $steps]);
    }

    public function reorderSteps(Request $request, Pipeline $pipeline): JsonResponse
    {
        $data = $request->validate([
            'version_id' => ['required', 'exists:pipeline_versions,id'],
            'from_position' => ['required', 'integer', 'min:1'],
            'to_position' => ['required', 'integer', 'min:1'],
        ]);

        $version = $this->requireSpecificVersion($pipeline, (int) $data['version_id']);
        $steps = $this->collectVersionSteps($version);

        $fromIndex = $data['from_position'] - 1;
        $toIndex = $data['to_position'] - 1;

        abort_if(! isset($steps[$fromIndex]), 422, 'Некорректная позиция шага.');
        $toIndex = max(0, min($toIndex, count($steps) - 1));

        $moved = $steps[$fromIndex];
        array_splice($steps, $fromIndex, 1);
        array_splice($steps, $toIndex, 0, [$moved]);

        $stepVersion = StepVersion::find($moved['step_version_id']);
        $stepName = $stepVersion?->name ?? 'Шаг';
        $entry = sprintf(
            '- Шаг %s перемещён с позиции %d на позицию %d',
            $stepName,
            $data['from_position'],
            $data['to_position'],
        );

        DB::transaction(function () use ($pipeline, $version, $entry, $steps): void {
            $this->createPipelineVersionFromPrevious(
                $pipeline,
                $version,
                changelogEntry: $entry,
                stepPayload: $steps,
            );
        });

        $pipeline->refresh()->load([
            'currentVersion.versionSteps.stepVersion.step',
            'currentVersion.versionSteps.stepVersion.inputStep.currentVersion',
            'steps.currentVersion',
        ]);

        return response()->json(['data' => $this->transformPipeline($pipeline)]);
    }

    private function assertStepBelongsToPipeline(Pipeline $pipeline, Step $step): void
    {
        abort_if($step->pipeline_id !== $pipeline->id, 404, 'Step does not belong to the pipeline.');
    }

    private function requireCurrentVersion(Pipeline $pipeline): PipelineVersion
    {
        $pipeline->loadMissing(
            'currentVersion.versionSteps.stepVersion.step',
            'currentVersion.versionSteps.stepVersion.inputStep.currentVersion',
        );
        $version = $pipeline->currentVersion;

        abort_if($version === null, 422, 'Pipeline has no current version.');

        return $version;
    }

    private function requireSpecificVersion(Pipeline $pipeline, int $versionId): PipelineVersion
    {
        /** @var PipelineVersion|null $version */
        $version = $pipeline->versions()->where('id', $versionId)->first();
        abort_if($version === null, 404, 'Pipeline version not found.');

        return $version;
    }

    /**
     * @return array<int, array{step_id:int, step_version_id:int}>
     */
    private function collectVersionSteps(PipelineVersion $version): array
    {
        $version->loadMissing('versionSteps.stepVersion.step', 'versionSteps.stepVersion.inputStep.currentVersion');

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
        ?string $changelogEntry = null,
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
                    'name' => $step->currentVersion?->name,
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
                            'name' => $stepVersion->name,
                        ],
                        'version' => StepVersionResource::make($stepVersion)->toArray(request()),
                    ];
                })
                ->all(),
        ];
    }

    private function resolveInputStep(Pipeline $pipeline, ?Step $currentStep, ?int $inputStepId): ?Step
    {
        if ($inputStepId === null) {
            return null;
        }

        $inputStep = Step::query()->find($inputStepId);

        abort_if($inputStep === null || $inputStep->pipeline_id !== $pipeline->id, 422, 'Источником может быть только шаг текущего пайплайна.');
        abort_if($currentStep !== null && $inputStep->id === $currentStep->id, 422, 'Шаг не может ссылаться сам на себя.');

        return $inputStep;
    }
}
