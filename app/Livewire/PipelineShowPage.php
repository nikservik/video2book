<?php

namespace App\Livewire;

use App\Actions\Pipeline\AddPipelineStepToVersionAction;
use App\Actions\Pipeline\CreatePipelineStepNewVersionAction;
use App\Actions\Pipeline\DeletePipelineStepFromVersionAction;
use App\Actions\Pipeline\SetCurrentPipelineVersionAction;
use App\Actions\Pipeline\TogglePipelineVersionArchiveStatusAction;
use App\Actions\Pipeline\UpdatePipelineStepVersionAction;
use App\Actions\Pipeline\UpdatePipelineVersionAction;
use App\Models\Pipeline;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\StepVersion;
use App\Services\Pipeline\PipelineDetailsQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PipelineShowPage extends Component
{
    public Pipeline $pipeline;

    public ?int $selectedVersionId = null;

    public bool $showStepEditModal = false;

    public ?int $editingStepVersionId = null;

    public string $editStepType = 'transcribe';

    public string $editStepName = '';

    public string $editStepDescription = '';

    public ?int $editStepInputStepId = null;

    public string $editStepModel = '';

    public float $editStepTemperature = 1.0;

    public string $editStepPrompt = '';

    public string $editStepChangelogEntry = '';

    public bool $showEditVersionModal = false;

    public string $editableVersionTitle = '';

    public string $editableVersionDescription = '';

    public bool $showChangelogModal = false;

    public bool $showStepCreateModal = false;

    public ?int $createStepInsertPosition = null;

    public string $createStepType = 'text';

    public string $createStepName = '';

    public string $createStepDescription = '';

    public ?int $createStepInputStepId = null;

    public string $createStepModel = '';

    public float $createStepTemperature = 1.0;

    public string $createStepPrompt = '';

    public bool $showDeleteStepAlert = false;

    public ?int $deletingStepVersionId = null;

    public string $deletingStepName = '';

    public function mount(Pipeline $pipeline): void
    {
        $this->pipeline = app(PipelineDetailsQuery::class)->get($pipeline);
        $this->selectedVersionId = $this->pipeline->current_version_id ?? $this->pipeline->versions->first()?->id;
    }

    public function selectVersion(int $versionId): void
    {
        $versionExists = $this->pipeline->versions
            ->contains(fn (PipelineVersion $version): bool => $version->id === $versionId);

        if (! $versionExists) {
            return;
        }

        $this->closeStepEditModal();
        $this->closeStepCreateModal();
        $this->closeEditVersionModal();
        $this->closeChangelogModal();
        $this->closeDeleteStepAlert();
        $this->selectedVersionId = $versionId;
    }

    public function openEditVersionModal(): void
    {
        $selectedVersion = $this->selectedVersion;

        if ($selectedVersion === null) {
            return;
        }

        $this->closeStepEditModal();
        $this->closeStepCreateModal();
        $this->closeChangelogModal();
        $this->closeDeleteStepAlert();
        $this->resetErrorBag();
        $this->editableVersionTitle = (string) $selectedVersion->title;
        $this->editableVersionDescription = (string) ($selectedVersion->description ?? '');
        $this->showEditVersionModal = true;
    }

    public function closeEditVersionModal(): void
    {
        $this->showEditVersionModal = false;
    }

    public function openChangelogModal(): void
    {
        if ($this->selectedVersion === null) {
            return;
        }

        $this->closeStepEditModal();
        $this->closeEditVersionModal();
        $this->closeStepCreateModal();
        $this->closeDeleteStepAlert();
        $this->showChangelogModal = true;
    }

    public function closeChangelogModal(): void
    {
        $this->showChangelogModal = false;
    }

    public function saveVersion(UpdatePipelineVersionAction $updatePipelineVersionAction): void
    {
        $selectedVersion = $this->selectedVersion;

        if ($selectedVersion === null) {
            return;
        }

        $validated = validator([
            'editableVersionTitle' => $this->editableVersionTitle,
            'editableVersionDescription' => blank($this->editableVersionDescription) ? null : trim($this->editableVersionDescription),
        ], [
            'editableVersionTitle' => ['required', 'string', 'max:255'],
            'editableVersionDescription' => ['nullable', 'string'],
        ], [], [
            'editableVersionTitle' => 'название версии',
            'editableVersionDescription' => 'описание версии',
        ])->validate();

        $updatedVersion = $updatePipelineVersionAction->handle(
            pipelineVersion: $selectedVersion,
            title: $validated['editableVersionTitle'],
            description: $validated['editableVersionDescription'],
        );

        $this->pipeline = app(PipelineDetailsQuery::class)->get($this->pipeline->fresh());
        $this->editableVersionTitle = $updatedVersion->title;
        $this->editableVersionDescription = (string) ($updatedVersion->description ?? '');
        $this->showEditVersionModal = false;
    }

    public function openStepEditModal(int $stepVersionId): void
    {
        /** @var array{position:int,step_version:StepVersion,input_step_name:string|null,model_label:string}|null $stepData */
        $stepData = collect($this->selectedVersionSteps)
            ->first(fn (array $item): bool => (int) $item['step_version']->id === $stepVersionId);

        if ($stepData === null) {
            return;
        }

        $this->closeStepCreateModal();
        $this->closeChangelogModal();
        $this->closeDeleteStepAlert();

        $stepVersion = $stepData['step_version'];

        $this->editingStepVersionId = $stepVersion->id;
        $this->editStepType = (string) ($stepVersion->type ?? 'text');
        $this->editStepName = (string) ($stepVersion->name ?? '');
        $this->editStepDescription = (string) ($stepVersion->description ?? '');
        $this->editStepInputStepId = $stepVersion->input_step_id === null
            ? null
            : (int) $stepVersion->input_step_id;
        $this->editStepModel = trim((string) data_get($stepVersion->settings, 'model', ''));
        $this->editStepTemperature = (float) data_get($stepVersion->settings, 'temperature', 1);
        $this->editStepPrompt = (string) ($stepVersion->prompt ?? '');
        $this->editStepChangelogEntry = '';

        $this->normalizeStepTypeByPosition();

        $inputStepAllowed = collect($this->stepEditInputStepOptions)
            ->contains(fn (array $option): bool => $option['id'] === $this->editStepInputStepId);

        if (! $inputStepAllowed) {
            $this->editStepInputStepId = null;
        }

        $this->syncStepModelAndTemperature();
        $this->showStepEditModal = true;
    }

    public function closeStepEditModal(): void
    {
        $this->showStepEditModal = false;
        $this->editingStepVersionId = null;
    }

    public function openStepCreateModal(int $afterStepVersionId): void
    {
        /** @var array{position:int,step_version:StepVersion,input_step_name:string|null,model_label:string}|null $stepData */
        $stepData = collect($this->selectedVersionSteps)
            ->first(fn (array $item): bool => (int) $item['step_version']->id === $afterStepVersionId);

        if ($stepData === null) {
            return;
        }

        $this->closeStepEditModal();
        $this->closeEditVersionModal();
        $this->closeStepCreateModal();
        $this->closeChangelogModal();
        $this->closeDeleteStepAlert();
        $this->resetErrorBag();

        $this->createStepInsertPosition = (int) $stepData['position'] + 1;
        $this->createStepType = $this->createStepInsertPosition === 1 ? 'transcribe' : 'text';
        $this->createStepName = '';
        $this->createStepDescription = '';
        $this->createStepInputStepId = $this->defaultCreateStepInputStepId();
        $this->createStepModel = '';
        $this->createStepTemperature = 1.0;
        $this->createStepPrompt = '';

        $this->syncCreateStepModelAndTemperature();
        $this->showStepCreateModal = true;
    }

    public function closeStepCreateModal(): void
    {
        $this->showStepCreateModal = false;
        $this->createStepInsertPosition = null;
    }

    public function setCreateStepType(string $type): void
    {
        if (! in_array($type, ['transcribe', 'text', 'glossary'], true)) {
            return;
        }

        if ($this->createStepInsertPosition !== 1 && $type === 'transcribe') {
            return;
        }

        if ($this->createStepInsertPosition === 1 && $type !== 'transcribe') {
            return;
        }

        $this->createStepType = $type;

        if ($type === 'transcribe') {
            $this->createStepInputStepId = null;
        } else {
            $inputStepAllowed = collect($this->createStepInputStepOptions)
                ->contains(fn (array $option): bool => $option['id'] === $this->createStepInputStepId);

            if (! $inputStepAllowed) {
                $this->createStepInputStepId = $this->defaultCreateStepInputStepId();
            }
        }

        $this->syncCreateStepModelAndTemperature();
    }

    public function updatedCreateStepModel(string $value): void
    {
        $this->createStepModel = trim($value);
        $this->syncCreateStepModelAndTemperature();
    }

    public function updatedCreateStepInputStepId($value): void
    {
        $this->createStepInputStepId = $value === '' || $value === null ? null : (int) $value;
    }

    public function updatedCreateStepTemperature($value): void
    {
        $this->createStepTemperature = round((float) $value, 1);
    }

    public function saveCreatedStep(AddPipelineStepToVersionAction $addPipelineStepToVersionAction): void
    {
        $selectedVersion = $this->selectedVersion;

        if (! $this->showStepCreateModal || $selectedVersion === null || $this->createStepInsertPosition === null) {
            return;
        }

        $validated = $this->validateStepCreateForm();
        $payload = $this->buildCreateStepPayload($validated);
        $selectedVersionWasCurrent = $this->selectedVersionIsCurrent;

        $newPipelineVersion = $addPipelineStepToVersionAction->handle(
            pipeline: $this->pipeline,
            sourceVersion: $selectedVersion,
            position: $this->createStepInsertPosition,
            payload: $payload,
            setAsCurrent: $selectedVersionWasCurrent,
        );

        $this->pipeline = app(PipelineDetailsQuery::class)->get($this->pipeline->fresh());
        $this->selectedVersionId = $newPipelineVersion->id;
        unset($this->selectedVersion, $this->selectedVersionSteps, $this->pipelineVersions);
        $this->closeStepCreateModal();
    }

    public function setEditStepType(string $type): void
    {
        if (! in_array($type, ['transcribe', 'text', 'glossary'], true)) {
            return;
        }

        $position = $this->editingStepPosition;

        if ($position === 1 && $type !== 'transcribe') {
            return;
        }

        if (($position ?? 0) > 1 && $type === 'transcribe') {
            return;
        }

        $this->editStepType = $type;

        if ($type === 'transcribe') {
            $this->editStepInputStepId = null;
        } else {
            $inputStepAllowed = collect($this->stepEditInputStepOptions)
                ->contains(fn (array $option): bool => $option['id'] === $this->editStepInputStepId);

            if (! $inputStepAllowed) {
                $this->editStepInputStepId = null;
            }
        }

        $this->syncStepModelAndTemperature();
    }

    public function updatedEditStepModel(string $value): void
    {
        $this->editStepModel = trim($value);
        $this->syncStepModelAndTemperature();
    }

    public function updatedEditStepInputStepId($value): void
    {
        $this->editStepInputStepId = $value === '' || $value === null ? null : (int) $value;
    }

    public function updatedEditStepTemperature($value): void
    {
        $this->editStepTemperature = round((float) $value, 1);
    }

    public function saveStep(UpdatePipelineStepVersionAction $updatePipelineStepVersionAction): void
    {
        $editingStepData = $this->editingStepData;

        if ($editingStepData === null) {
            return;
        }

        $validated = $this->validateStepEditForm(requireChangelog: false);
        $payload = $this->buildStepPayload($editingStepData['step_version'], $validated);

        $updatePipelineStepVersionAction->handle(
            $editingStepData['step_version'],
            $payload,
            $this->editingStepIsDraft,
        );

        $this->pipeline = app(PipelineDetailsQuery::class)->get($this->pipeline->fresh());
        unset(
            $this->selectedVersion,
            $this->selectedVersionSteps,
            $this->pipelineVersions,
            $this->selectedVersionHasDraftSteps,
            $this->selectedVersionIsArchived
        );
        $this->closeStepEditModal();
    }

    public function saveStepAsNewVersion(CreatePipelineStepNewVersionAction $createPipelineStepNewVersionAction): void
    {
        $editingStepData = $this->editingStepData;
        $selectedVersion = $this->selectedVersion;

        if ($editingStepData === null || $selectedVersion === null || $this->editingStepIsDraft) {
            return;
        }

        $validated = $this->validateStepEditForm(requireChangelog: true);
        $payload = $this->buildStepPayload($editingStepData['step_version'], $validated);
        $selectedVersionWasCurrent = $this->selectedVersionIsCurrent;

        $newPipelineVersion = $createPipelineStepNewVersionAction->handle(
            pipeline: $this->pipeline,
            sourceVersion: $selectedVersion,
            sourceStepVersion: $editingStepData['step_version'],
            payload: $payload,
            changelogEntry: trim((string) $validated['editStepChangelogEntry']),
            setAsCurrent: $selectedVersionWasCurrent,
        );

        $this->pipeline = app(PipelineDetailsQuery::class)->get($this->pipeline->fresh());
        $this->selectedVersionId = $newPipelineVersion->id;
        $this->closeStepEditModal();
    }

    public function removeStep(int $stepVersionId): void
    {
        $this->openDeleteStepAlert($stepVersionId);
    }

    public function openDeleteStepAlert(int $stepVersionId): void
    {
        $selectedVersion = $this->selectedVersion;

        if ($selectedVersion === null) {
            return;
        }

        /** @var array{position:int,step_version:StepVersion,input_step_name:string|null,model_label:string}|null $stepData */
        $stepData = collect($this->selectedVersionSteps)
            ->first(fn (array $item): bool => (int) $item['step_version']->id === $stepVersionId);

        if ($stepData === null || (int) $stepData['position'] === 1) {
            return;
        }

        $this->closeStepEditModal();
        $this->closeEditVersionModal();
        $this->closeStepCreateModal();
        $this->closeChangelogModal();

        $this->deletingStepVersionId = (int) $stepData['step_version']->id;
        $this->deletingStepName = (string) ($stepData['step_version']->name ?? 'Без названия шага');
        $this->showDeleteStepAlert = true;
    }

    public function closeDeleteStepAlert(): void
    {
        $this->showDeleteStepAlert = false;
        $this->deletingStepVersionId = null;
        $this->deletingStepName = '';
    }

    public function confirmDeleteStep(DeletePipelineStepFromVersionAction $deletePipelineStepFromVersionAction): void
    {
        $selectedVersion = $this->pipeline->versions
            ->first(fn (PipelineVersion $version): bool => (int) $version->id === (int) $this->selectedVersionId);

        if ($selectedVersion === null || $this->deletingStepVersionId === null) {
            return;
        }

        $selectedVersion->loadMissing('versionSteps.stepVersion');

        /** @var PipelineVersionStep|null $versionStep */
        $versionStep = $selectedVersion->versionSteps
            ->sortBy('position')
            ->first(fn (PipelineVersionStep $item): bool => (int) $item->step_version_id === (int) $this->deletingStepVersionId);

        $stepVersion = $versionStep?->stepVersion;

        if ($versionStep === null || (int) $versionStep->position === 1 || $stepVersion === null) {
            $this->closeDeleteStepAlert();

            return;
        }

        $selectedVersionWasCurrent = (int) $selectedVersion->id === (int) $this->pipeline->current_version_id;

        $newPipelineVersion = $deletePipelineStepFromVersionAction->handle(
            pipeline: $this->pipeline,
            sourceVersion: $selectedVersion,
            removedStepVersion: $stepVersion,
            setAsCurrent: $selectedVersionWasCurrent,
        );

        $this->pipeline = app(PipelineDetailsQuery::class)->get($this->pipeline->fresh());
        $this->selectedVersionId = $newPipelineVersion->id;
        $this->closeDeleteStepAlert();
    }

    public function toggleSelectedVersionArchiveStatus(TogglePipelineVersionArchiveStatusAction $action): void
    {
        $selectedVersion = $this->selectedVersion;

        if ($selectedVersion === null) {
            return;
        }

        if ($this->selectedVersionIsArchived && $this->selectedVersionHasDraftSteps) {
            return;
        }

        $action->handle($selectedVersion);

        $this->pipeline = app(PipelineDetailsQuery::class)->get($this->pipeline);
        unset(
            $this->selectedVersion,
            $this->selectedVersionSteps,
            $this->pipelineVersions,
            $this->selectedVersionIsArchived,
            $this->selectedVersionHasDraftSteps
        );
    }

    public function makeSelectedVersionCurrent(SetCurrentPipelineVersionAction $action): void
    {
        $selectedVersion = $this->selectedVersion;

        if ($selectedVersion === null || $this->selectedVersionIsCurrent || $this->selectedVersionIsArchived) {
            return;
        }

        $action->handle($this->pipeline, $selectedVersion);

        $this->pipeline = app(PipelineDetailsQuery::class)->get($this->pipeline);
    }

    public function getSelectedVersionProperty(): ?PipelineVersion
    {
        return $this->pipeline->versions
            ->first(fn (PipelineVersion $version): bool => $version->id === $this->selectedVersionId);
    }

    /**
     * @return array<int, array{
     *     position:int,
     *     step_version: StepVersion,
     *     input_step_name: string|null,
     *     model_label: string
     * }>
     */
    public function getSelectedVersionStepsProperty(): array
    {
        $selectedVersion = $this->selectedVersion;

        if ($selectedVersion === null) {
            return [];
        }

        $stepNameByStepId = $selectedVersion->versionSteps
            ->mapWithKeys(fn ($versionStep): array => [
                (int) ($versionStep->stepVersion?->step_id ?? 0) => $versionStep->stepVersion?->name,
            ]);

        return $selectedVersion->versionSteps
            ->map(function ($versionStep) use ($stepNameByStepId): ?array {
                $stepVersion = $versionStep->stepVersion;

                if ($stepVersion === null) {
                    return null;
                }

                $inputStepName = null;

                if ($stepVersion->input_step_id !== null) {
                    $inputStepName = $stepNameByStepId->get((int) $stepVersion->input_step_id)
                        ?? $stepVersion->inputStepCurrentVersion()?->name;
                }

                return [
                    'position' => (int) $versionStep->position,
                    'step_version' => $stepVersion,
                    'input_step_name' => $inputStepName,
                    'model_label' => $this->stepModelLabel($stepVersion),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function getSelectedVersionTitleProperty(): string
    {
        return $this->selectedVersion?->title ?? 'Без названия';
    }

    public function getSelectedVersionNumberProperty(): string
    {
        return (string) ($this->selectedVersion?->version ?? '—');
    }

    public function getSelectedVersionChangelogProperty(): string
    {
        $changelog = trim((string) ($this->selectedVersion?->changelog ?? ''));

        if ($changelog === '') {
            return 'Для этой версии changelog пока пуст.';
        }

        return $changelog;
    }

    /**
     * @return array{position:int,step_version:StepVersion,input_step_name:string|null,model_label:string}|null
     */
    public function getEditingStepDataProperty(): ?array
    {
        if ($this->editingStepVersionId === null) {
            return null;
        }

        /** @var array{position:int,step_version:StepVersion,input_step_name:string|null,model_label:string}|null $stepData */
        $stepData = collect($this->selectedVersionSteps)
            ->first(fn (array $item): bool => (int) $item['step_version']->id === $this->editingStepVersionId);

        return $stepData;
    }

    public function getEditingStepPositionProperty(): ?int
    {
        return $this->editingStepData['position'] ?? null;
    }

    /**
     * @return array<int, array{id:int,name:string}>
     */
    public function getStepEditInputStepOptionsProperty(): array
    {
        $position = $this->editingStepPosition;

        if ($position === null || $position <= 1) {
            return [];
        }

        return collect($this->selectedVersionSteps)
            ->filter(fn (array $stepData): bool => (int) $stepData['position'] < $position)
            ->filter(fn (array $stepData): bool => in_array((string) $stepData['step_version']->type, ['text', 'transcribe'], true))
            ->map(fn (array $stepData): array => [
                'id' => (int) $stepData['step_version']->step_id,
                'name' => (string) ($stepData['step_version']->name ?? 'Без названия шага'),
            ])
            ->values()
            ->all();
    }

    public function getSelectedEditStepInputStepLabelProperty(): string
    {
        return data_get(
            collect($this->stepEditInputStepOptions)->firstWhere('id', $this->editStepInputStepId),
            'name',
            'Не выбрано'
        );
    }

    /**
     * @return array<int, array{id:string,label:string}>
     */
    public function getStepEditModelOptionsProperty(): array
    {
        if ($this->editStepType === 'transcribe') {
            return [
                ['id' => 'whisper-1', 'label' => 'whisper-1'],
                ['id' => 'gemini-3-flash-preview', 'label' => 'gemini-3-flash-preview'],
            ];
        }

        $openAiModels = array_keys((array) config('pricing.providers.openai.models', []));
        $anthropicModels = array_keys((array) config('pricing.providers.anthropic.models', []));
        $geminiModels = array_keys((array) config('pricing.providers.gemini.models', []));

        $models = collect([...$openAiModels, ...$anthropicModels, ...$geminiModels])
            ->filter(fn (string $model): bool => $model !== 'whisper-1')
            ->unique()
            ->values();

        return $models
            ->map(fn (string $model): array => [
                'id' => $model,
                'label' => $model,
            ])
            ->all();
    }

    public function getSelectedEditStepModelLabelProperty(): string
    {
        return data_get(
            collect($this->stepEditModelOptions)->firstWhere('id', $this->editStepModel),
            'label',
            'Выберите модель'
        );
    }

    /**
     * @return array<int, array{id:int,name:string}>
     */
    public function getCreateStepInputStepOptionsProperty(): array
    {
        $position = $this->createStepInsertPosition;

        if ($position === null || $position <= 1) {
            return [];
        }

        return collect($this->selectedVersionSteps)
            ->filter(fn (array $stepData): bool => (int) $stepData['position'] < $position)
            ->filter(fn (array $stepData): bool => in_array((string) $stepData['step_version']->type, ['text', 'transcribe'], true))
            ->map(fn (array $stepData): array => [
                'id' => (int) $stepData['step_version']->step_id,
                'name' => (string) ($stepData['step_version']->name ?? 'Без названия шага'),
            ])
            ->values()
            ->all();
    }

    public function getSelectedCreateStepInputStepLabelProperty(): string
    {
        return data_get(
            collect($this->createStepInputStepOptions)->firstWhere('id', $this->createStepInputStepId),
            'name',
            'Не выбрано'
        );
    }

    /**
     * @return array<int, array{id:string,label:string}>
     */
    public function getCreateStepModelOptionsProperty(): array
    {
        if ($this->createStepType === 'transcribe') {
            return [
                ['id' => 'whisper-1', 'label' => 'whisper-1'],
                ['id' => 'gemini-3-flash-preview', 'label' => 'gemini-3-flash-preview'],
            ];
        }

        $openAiModels = array_keys((array) config('pricing.providers.openai.models', []));
        $anthropicModels = array_keys((array) config('pricing.providers.anthropic.models', []));
        $geminiModels = array_keys((array) config('pricing.providers.gemini.models', []));

        $models = collect([...$openAiModels, ...$anthropicModels, ...$geminiModels])
            ->filter(fn (string $model): bool => $model !== 'whisper-1')
            ->unique()
            ->values();

        return $models
            ->map(fn (string $model): array => [
                'id' => $model,
                'label' => $model,
            ])
            ->all();
    }

    public function getSelectedCreateStepModelLabelProperty(): string
    {
        return data_get(
            collect($this->createStepModelOptions)->firstWhere('id', $this->createStepModel),
            'label',
            'Выберите модель'
        );
    }

    /**
     * @return array{min:float,max:float,step:float,disabled:bool,default:float}
     */
    public function getCreateStepTemperatureConfigProperty(): array
    {
        $model = $this->createStepModel;

        if (str_starts_with($model, 'gpt-')) {
            return [
                'min' => 0.0,
                'max' => 2.0,
                'step' => 0.1,
                'disabled' => true,
                'default' => 1.0,
            ];
        }

        if (str_starts_with($model, 'claude-')) {
            return [
                'min' => 0.0,
                'max' => 1.0,
                'step' => 0.1,
                'disabled' => false,
                'default' => 1.0,
            ];
        }

        if (str_starts_with($model, 'gemini-')) {
            return [
                'min' => 0.0,
                'max' => 2.0,
                'step' => 0.1,
                'disabled' => false,
                'default' => 1.0,
            ];
        }

        if (str_starts_with($model, 'whisper-')) {
            return [
                'min' => 0.0,
                'max' => 2.0,
                'step' => 0.1,
                'disabled' => true,
                'default' => 1.0,
            ];
        }

        return [
            'min' => 0.0,
            'max' => 2.0,
            'step' => 0.1,
            'disabled' => false,
            'default' => 1.0,
        ];
    }

    /**
     * @return array{min:float,max:float,step:float,disabled:bool,default:float}
     */
    public function getEditStepTemperatureConfigProperty(): array
    {
        $model = $this->editStepModel;

        if (str_starts_with($model, 'gpt-')) {
            return [
                'min' => 0.0,
                'max' => 2.0,
                'step' => 0.1,
                'disabled' => true,
                'default' => 1.0,
            ];
        }

        if (str_starts_with($model, 'claude-')) {
            return [
                'min' => 0.0,
                'max' => 1.0,
                'step' => 0.1,
                'disabled' => false,
                'default' => 1.0,
            ];
        }

        if (str_starts_with($model, 'gemini-')) {
            return [
                'min' => 0.0,
                'max' => 2.0,
                'step' => 0.1,
                'disabled' => false,
                'default' => 1.0,
            ];
        }

        if (str_starts_with($model, 'whisper-')) {
            return [
                'min' => 0.0,
                'max' => 2.0,
                'step' => 0.1,
                'disabled' => true,
                'default' => 1.0,
            ];
        }

        return [
            'min' => 0.0,
            'max' => 2.0,
            'step' => 0.1,
            'disabled' => false,
            'default' => 1.0,
        ];
    }

    public function getSelectedVersionIsArchivedProperty(): bool
    {
        return $this->selectedVersion?->status === 'archived';
    }

    public function getSelectedVersionIsCurrentProperty(): bool
    {
        $selectedVersionId = (int) ($this->selectedVersion?->id ?? 0);
        $currentVersionId = (int) ($this->pipeline->current_version_id ?? 0);

        return $selectedVersionId !== 0 && $selectedVersionId === $currentVersionId;
    }

    public function getSelectedVersionHasDraftStepsProperty(): bool
    {
        return collect($this->selectedVersionSteps)
            ->contains(
                fn (array $stepData): bool => (string) ($stepData['step_version']->status ?? '') === 'draft'
            );
    }

    public function getEditingStepIsDraftProperty(): bool
    {
        $editingStepData = $this->editingStepData;

        if ($editingStepData === null) {
            return false;
        }

        return (string) ($editingStepData['step_version']->status ?? '') === 'draft';
    }

    /**
     * @return \Illuminate\Support\Collection<int, PipelineVersion>
     */
    public function getPipelineVersionsProperty(): Collection
    {
        return $this->pipeline->versions
            ->sortByDesc('version')
            ->values();
    }

    private function stepModelLabel(StepVersion $stepVersion): string
    {
        $model = trim((string) data_get($stepVersion->settings, 'model', ''));

        return $model !== '' ? $model : 'Модель не указана';
    }

    private function normalizeStepTypeByPosition(): void
    {
        $position = $this->editingStepPosition;

        if ($position === null) {
            return;
        }

        if ($position === 1) {
            $this->editStepType = 'transcribe';

            return;
        }

        if (! in_array($this->editStepType, ['text', 'glossary'], true)) {
            $this->editStepType = 'text';
        }
    }

    private function syncStepModelAndTemperature(): void
    {
        $availableModels = collect($this->stepEditModelOptions);

        if ($availableModels->isEmpty()) {
            $this->editStepModel = '';
            $this->editStepTemperature = 1.0;

            return;
        }

        $currentModelAllowed = $availableModels
            ->contains(fn (array $model): bool => $model['id'] === $this->editStepModel);

        if (! $currentModelAllowed) {
            $this->editStepModel = (string) ($availableModels->first()['id'] ?? '');
        }

        $temperatureConfig = $this->editStepTemperatureConfig;

        if ($temperatureConfig['disabled']) {
            $this->editStepTemperature = $temperatureConfig['default'];

            return;
        }

        $this->editStepTemperature = max(
            $temperatureConfig['min'],
            min($temperatureConfig['max'], round($this->editStepTemperature, 1))
        );
    }

    private function syncCreateStepModelAndTemperature(): void
    {
        $availableModels = collect($this->createStepModelOptions);

        if ($availableModels->isEmpty()) {
            $this->createStepModel = '';
            $this->createStepTemperature = 1.0;

            return;
        }

        $currentModelAllowed = $availableModels
            ->contains(fn (array $model): bool => $model['id'] === $this->createStepModel);

        if (! $currentModelAllowed) {
            $this->createStepModel = (string) ($availableModels->first()['id'] ?? '');
        }

        $temperatureConfig = $this->createStepTemperatureConfig;

        if ($temperatureConfig['disabled']) {
            $this->createStepTemperature = $temperatureConfig['default'];

            return;
        }

        $this->createStepTemperature = max(
            $temperatureConfig['min'],
            min($temperatureConfig['max'], round($this->createStepTemperature, 1))
        );
    }

    private function defaultCreateStepInputStepId(): ?int
    {
        $defaultInputStep = collect($this->createStepInputStepOptions)->last();

        if ($defaultInputStep === null) {
            return null;
        }

        return (int) $defaultInputStep['id'];
    }

    /**
     * @return array{
     *   createStepType:string,
     *   createStepName:string,
     *   createStepDescription:?string,
     *   createStepInputStepId:?int,
     *   createStepModel:string,
     *   createStepTemperature:float,
     *   createStepPrompt:?string
     * }
     */
    private function validateStepCreateForm(): array
    {
        $temperatureConfig = $this->createStepTemperatureConfig;
        $inputStepIds = collect($this->createStepInputStepOptions)->pluck('id')->all();
        $modelIds = collect($this->createStepModelOptions)->pluck('id')->all();
        $position = $this->createStepInsertPosition;

        $temperatureRules = ['required', 'numeric'];

        if (! $temperatureConfig['disabled']) {
            $temperatureRules[] = 'min:'.$temperatureConfig['min'];
            $temperatureRules[] = 'max:'.$temperatureConfig['max'];
        }

        $validated = validator([
            'createStepType' => $this->createStepType,
            'createStepName' => $this->createStepName,
            'createStepDescription' => blank($this->createStepDescription) ? null : trim($this->createStepDescription),
            'createStepInputStepId' => $this->createStepInputStepId,
            'createStepModel' => $this->createStepModel,
            'createStepTemperature' => $this->createStepTemperature,
            'createStepPrompt' => blank($this->createStepPrompt) ? null : $this->createStepPrompt,
        ], [
            'createStepType' => [
                'required',
                Rule::in(['transcribe', 'text', 'glossary']),
                function (string $attribute, mixed $value, \Closure $fail) use ($position): void {
                    if ($position === 1 && $value !== 'transcribe') {
                        $fail('Первый шаг может быть только транскрибацией.');
                    }

                    if (($position ?? 0) > 1 && $value === 'transcribe') {
                        $fail('Начиная со второго шага транскрибация недоступна.');
                    }
                },
            ],
            'createStepName' => ['required', 'string', 'max:255'],
            'createStepDescription' => ['nullable', 'string'],
            'createStepInputStepId' => ['nullable', 'integer', Rule::in($inputStepIds)],
            'createStepModel' => ['required', 'string', Rule::in($modelIds)],
            'createStepTemperature' => $temperatureRules,
            'createStepPrompt' => ['nullable', 'string'],
        ], [], [
            'createStepType' => 'тип шага',
            'createStepName' => 'название шага',
            'createStepDescription' => 'короткое описание',
            'createStepInputStepId' => 'шаг-источник',
            'createStepModel' => 'модель',
            'createStepTemperature' => 'температура',
            'createStepPrompt' => 'промт',
        ])->validate();

        if ($validated['createStepType'] === 'transcribe') {
            $validated['createStepInputStepId'] = null;
        }

        if ($temperatureConfig['disabled']) {
            $validated['createStepTemperature'] = $temperatureConfig['default'];
        } else {
            $validated['createStepTemperature'] = max(
                $temperatureConfig['min'],
                min($temperatureConfig['max'], round((float) $validated['createStepTemperature'], 1))
            );
        }

        return $validated;
    }

    /**
     * @return array{
     *   editStepType:string,
     *   editStepName:string,
     *   editStepDescription:?string,
     *   editStepInputStepId:?int,
     *   editStepModel:string,
     *   editStepTemperature:float,
     *   editStepPrompt:?string,
     *   editStepChangelogEntry:?string
     * }
     */
    private function validateStepEditForm(bool $requireChangelog): array
    {
        $temperatureConfig = $this->editStepTemperatureConfig;
        $inputStepIds = collect($this->stepEditInputStepOptions)->pluck('id')->all();
        $modelIds = collect($this->stepEditModelOptions)->pluck('id')->all();
        $position = $this->editingStepPosition;

        $temperatureRules = ['required', 'numeric'];

        if (! $temperatureConfig['disabled']) {
            $temperatureRules[] = 'min:'.$temperatureConfig['min'];
            $temperatureRules[] = 'max:'.$temperatureConfig['max'];
        }

        $validated = validator([
            'editStepType' => $this->editStepType,
            'editStepName' => $this->editStepName,
            'editStepDescription' => blank($this->editStepDescription) ? null : trim($this->editStepDescription),
            'editStepInputStepId' => $this->editStepInputStepId,
            'editStepModel' => $this->editStepModel,
            'editStepTemperature' => $this->editStepTemperature,
            'editStepPrompt' => blank($this->editStepPrompt) ? null : $this->editStepPrompt,
            'editStepChangelogEntry' => blank($this->editStepChangelogEntry) ? null : trim($this->editStepChangelogEntry),
        ], [
            'editStepType' => [
                'required',
                Rule::in(['transcribe', 'text', 'glossary']),
                function (string $attribute, mixed $value, \Closure $fail) use ($position): void {
                    if ($position === 1 && $value !== 'transcribe') {
                        $fail('Первый шаг может быть только транскрибацией.');
                    }

                    if (($position ?? 0) > 1 && $value === 'transcribe') {
                        $fail('Начиная со второго шага транскрибация недоступна.');
                    }
                },
            ],
            'editStepName' => ['required', 'string', 'max:255'],
            'editStepDescription' => ['nullable', 'string'],
            'editStepInputStepId' => ['nullable', 'integer', Rule::in($inputStepIds)],
            'editStepModel' => ['required', 'string', Rule::in($modelIds)],
            'editStepTemperature' => $temperatureRules,
            'editStepPrompt' => ['nullable', 'string'],
            'editStepChangelogEntry' => $requireChangelog ? ['required', 'string'] : ['nullable', 'string'],
        ], [], [
            'editStepType' => 'тип шага',
            'editStepName' => 'название шага',
            'editStepDescription' => 'короткое описание',
            'editStepInputStepId' => 'шаг-источник',
            'editStepModel' => 'модель',
            'editStepTemperature' => 'температура',
            'editStepPrompt' => 'промт',
            'editStepChangelogEntry' => 'описание изменения',
        ])->validate();

        if ($validated['editStepType'] === 'transcribe') {
            $validated['editStepInputStepId'] = null;
        }

        if ($temperatureConfig['disabled']) {
            $validated['editStepTemperature'] = $temperatureConfig['default'];
        } else {
            $validated['editStepTemperature'] = max(
                $temperatureConfig['min'],
                min($temperatureConfig['max'], round((float) $validated['editStepTemperature'], 1))
            );
        }

        return $validated;
    }

    /**
     * @param  array{
     *   createStepType:string,
     *   createStepName:string,
     *   createStepDescription:?string,
     *   createStepInputStepId:?int,
     *   createStepModel:string,
     *   createStepTemperature:float,
     *   createStepPrompt:?string
     * }  $validated
     * @return array{name:string,type:string,description:?string,prompt:?string,settings:array<string,mixed>,input_step_id:?int}
     */
    private function buildCreateStepPayload(array $validated): array
    {
        return [
            'name' => trim($validated['createStepName']),
            'type' => $validated['createStepType'],
            'description' => $validated['createStepDescription'],
            'prompt' => $validated['createStepPrompt'],
            'settings' => [
                'model' => $validated['createStepModel'],
                'provider' => $this->providerByModel($validated['createStepModel'], $validated['createStepType']),
                'temperature' => $validated['createStepTemperature'],
            ],
            'input_step_id' => $validated['createStepInputStepId'],
        ];
    }

    /**
     * @param  array{
     *   editStepType:string,
     *   editStepName:string,
     *   editStepDescription:?string,
     *   editStepInputStepId:?int,
     *   editStepModel:string,
     *   editStepTemperature:float,
     *   editStepPrompt:?string,
     *   editStepChangelogEntry:?string
     * }  $validated
     * @return array{name:string,type:string,description:?string,prompt:?string,settings:array<string,mixed>,input_step_id:?int}
     */
    private function buildStepPayload(StepVersion $stepVersion, array $validated): array
    {
        $settings = is_array($stepVersion->settings) ? $stepVersion->settings : [];

        $settings['model'] = $validated['editStepModel'];
        $settings['provider'] = $this->providerByModel($validated['editStepModel'], $validated['editStepType']);
        $settings['temperature'] = $validated['editStepTemperature'];

        return [
            'name' => trim($validated['editStepName']),
            'type' => $validated['editStepType'],
            'description' => $validated['editStepDescription'],
            'prompt' => $validated['editStepPrompt'],
            'settings' => $settings,
            'input_step_id' => $validated['editStepInputStepId'],
        ];
    }

    private function providerByModel(string $model, string $stepType): string
    {
        if ($stepType === 'transcribe' && str_starts_with($model, 'whisper-')) {
            return 'openai';
        }

        if (str_starts_with($model, 'gpt-')) {
            return 'openai';
        }

        if (str_starts_with($model, 'claude-')) {
            return 'anthropic';
        }

        if (str_starts_with($model, 'gemini-')) {
            return 'gemini';
        }

        return 'openai';
    }

    public function render(): View
    {
        return view('pages.pipeline-show-page', [
            'pipeline' => $this->pipeline,
            'selectedVersion' => $this->selectedVersion,
            'selectedVersionSteps' => $this->selectedVersionSteps,
        ])->layout('layouts.app', [
            'title' => 'Пайплайн | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [
                ['label' => 'Пайплайны', 'url' => route('pipelines.index')],
                ['label' => $this->selectedVersionTitle, 'current' => true],
            ],
        ]);
    }
}
