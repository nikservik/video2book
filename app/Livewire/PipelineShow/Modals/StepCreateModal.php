<?php

namespace App\Livewire\PipelineShow\Modals;

use App\Actions\Pipeline\AddPipelineStepToVersionAction;
use App\Livewire\PipelineShow\Modals\Concerns\ResolvesPipelineVersionData;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class StepCreateModal extends Component
{
    use ResolvesPipelineVersionData;

    public bool $show = false;

    public ?int $createStepInsertPosition = null;

    public string $createStepType = 'text';

    public string $createStepName = '';

    public string $createStepDescription = '';

    public ?int $createStepInputStepId = null;

    public string $createStepModel = '';

    public float $createStepTemperature = 1.0;

    public string $createStepPrompt = '';

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

    #[On('pipeline-show:step-create-modal-open')]
    public function open(int $afterStepVersionId): void
    {
        $this->refreshPipelineData();

        /** @var array{position:int,step_version:\App\Models\StepVersion,input_step_name:string|null,model_label:string}|null $stepData */
        $stepData = collect($this->resolveSelectedVersionSteps())
            ->first(fn (array $item): bool => (int) $item['step_version']->id === $afterStepVersionId);

        if ($stepData === null) {
            return;
        }

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

        $this->dispatch('pipeline-show:close-modals', except: 'step-create');
        $this->show = true;
    }

    #[On('pipeline-show:close-modals')]
    public function closeByEvent(?string $except = null): void
    {
        if ($except === 'step-create') {
            return;
        }

        $this->close();
    }

    public function close(): void
    {
        $this->show = false;
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
        $selectedVersion = $this->resolveSelectedVersion();

        if (! $this->show || $selectedVersion === null || $this->createStepInsertPosition === null) {
            return;
        }

        $validated = $this->validateStepCreateForm();
        $payload = $this->buildCreateStepPayload($validated);
        $selectedVersionWasCurrent = $this->resolveSelectedVersionIsCurrent();

        $newPipelineVersion = $addPipelineStepToVersionAction->handle(
            pipeline: $this->pipeline,
            sourceVersion: $selectedVersion,
            position: $this->createStepInsertPosition,
            payload: $payload,
            setAsCurrent: $selectedVersionWasCurrent,
        );

        $this->dispatch('pipeline-show:pipeline-updated', selectedVersionId: (int) $newPipelineVersion->id);
        $this->close();
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
        return view('pipeline-show.modals.step-create-modal');
    }
}
