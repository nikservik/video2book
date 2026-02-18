<?php

namespace App\Livewire\PipelineShow\Modals;

use App\Actions\Pipeline\CreatePipelineStepNewVersionAction;
use App\Actions\Pipeline\UpdatePipelineStepVersionAction;
use App\Livewire\PipelineShow\Modals\Concerns\ResolvesPipelineVersionData;
use App\Models\StepVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class StepEditModal extends Component
{
    use ResolvesPipelineVersionData;

    public bool $show = false;

    public ?int $editingStepVersionId = null;

    public string $editStepType = 'transcribe';

    public string $editStepName = '';

    public string $editStepDescription = '';

    public ?int $editStepInputStepId = null;

    public string $editStepModel = '';

    public float $editStepTemperature = 1.0;

    public string $editStepPrompt = '';

    public string $editStepChangelogEntry = '';

    public function mount(int $pipelineId, ?int $selectedVersionId = null): void
    {
        $this->pipelineId = $pipelineId;
        $this->selectedVersionId = $selectedVersionId;
        $this->refreshPipelineData();
    }

    public function updatedSelectedVersionId($value): void
    {
        $this->selectedVersionId = $value === null || $value === '' ? null : (int) $value;
        $this->refreshPipelineData();
    }

    #[On('pipeline-show:step-edit-modal-open')]
    public function open(int $stepVersionId): void
    {
        $this->refreshPipelineData();

        /** @var array{position:int,step_version:StepVersion,input_step_name:string|null,model_label:string}|null $stepData */
        $stepData = collect($this->resolveSelectedVersionSteps())
            ->first(fn (array $item): bool => (int) $item['step_version']->id === $stepVersionId);

        if ($stepData === null) {
            return;
        }

        $stepVersion = $stepData['step_version'];

        $this->resetErrorBag();
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

        $this->dispatch('pipeline-show:close-modals', except: 'step-edit');
        $this->show = true;
    }

    #[On('pipeline-show:close-modals')]
    public function closeByEvent(?string $except = null): void
    {
        if ($except === 'step-edit') {
            return;
        }

        $this->close();
    }

    public function close(): void
    {
        $this->show = false;
        $this->editingStepVersionId = null;
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

        if (! $this->show || $editingStepData === null) {
            return;
        }

        $validated = $this->validateStepEditForm(requireChangelog: false);
        $payload = $this->buildStepPayload($editingStepData['step_version'], $validated);

        $updatePipelineStepVersionAction->handle(
            $editingStepData['step_version'],
            $payload,
            $this->editingStepIsDraft,
        );

        $this->dispatch('pipeline-show:pipeline-updated', selectedVersionId: (int) ($this->resolveSelectedVersion()?->id ?? 0));
        $this->close();
    }

    public function saveStepAsNewVersion(CreatePipelineStepNewVersionAction $createPipelineStepNewVersionAction): void
    {
        $editingStepData = $this->editingStepData;
        $selectedVersion = $this->resolveSelectedVersion();

        if (! $this->show || $editingStepData === null || $selectedVersion === null || $this->editingStepIsDraft) {
            return;
        }

        $validated = $this->validateStepEditForm(requireChangelog: true);
        $payload = $this->buildStepPayload($editingStepData['step_version'], $validated);
        $selectedVersionWasCurrent = $this->resolveSelectedVersionIsCurrent();

        $newPipelineVersion = $createPipelineStepNewVersionAction->handle(
            pipeline: $this->pipeline,
            sourceVersion: $selectedVersion,
            sourceStepVersion: $editingStepData['step_version'],
            payload: $payload,
            changelogEntry: trim((string) $validated['editStepChangelogEntry']),
            setAsCurrent: $selectedVersionWasCurrent,
        );

        $this->dispatch('pipeline-show:pipeline-updated', selectedVersionId: (int) $newPipelineVersion->id);
        $this->close();
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
        $stepData = collect($this->resolveSelectedVersionSteps())
            ->first(fn (array $item): bool => (int) $item['step_version']->id === $this->editingStepVersionId);

        return $stepData;
    }

    public function getEditingStepPositionProperty(): ?int
    {
        return $this->editingStepData['position'] ?? null;
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
     * @return array<int, array{id:int,name:string}>
     */
    public function getStepEditInputStepOptionsProperty(): array
    {
        $position = $this->editingStepPosition;

        if ($position === null || $position <= 1) {
            return [];
        }

        return collect($this->resolveSelectedVersionSteps())
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
        return view('pipeline-show.modals.step-edit-modal');
    }
}
