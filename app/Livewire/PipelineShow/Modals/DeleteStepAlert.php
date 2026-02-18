<?php

namespace App\Livewire\PipelineShow\Modals;

use App\Actions\Pipeline\DeletePipelineStepFromVersionAction;
use App\Livewire\PipelineShow\Modals\Concerns\ResolvesPipelineVersionData;
use App\Models\PipelineVersionStep;
use App\Models\StepVersion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class DeleteStepAlert extends Component
{
    use ResolvesPipelineVersionData;

    public bool $show = false;

    public ?int $deletingStepVersionId = null;

    public string $deletingStepName = '';

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

    #[On('pipeline-show:delete-step-alert-open')]
    public function open(int $stepVersionId): void
    {
        $this->refreshPipelineData();

        /** @var array{position:int,step_version:StepVersion,input_step_name:string|null,model_label:string}|null $stepData */
        $stepData = collect($this->resolveSelectedVersionSteps())
            ->first(fn (array $item): bool => (int) $item['step_version']->id === $stepVersionId);

        if ($stepData === null || (int) $stepData['position'] === 1) {
            return;
        }

        $this->deletingStepVersionId = (int) $stepData['step_version']->id;
        $this->deletingStepName = (string) ($stepData['step_version']->name ?? 'Без названия шага');

        $this->dispatch('pipeline-show:close-modals', except: 'delete-step');
        $this->show = true;
    }

    #[On('pipeline-show:close-modals')]
    public function closeByEvent(?string $except = null): void
    {
        if ($except === 'delete-step') {
            return;
        }

        $this->close();
    }

    public function close(): void
    {
        $this->show = false;
        $this->deletingStepVersionId = null;
        $this->deletingStepName = '';
    }

    public function confirmDeleteStep(DeletePipelineStepFromVersionAction $deletePipelineStepFromVersionAction): void
    {
        $selectedVersion = $this->resolveSelectedVersion();

        if (! $this->show || $selectedVersion === null || $this->deletingStepVersionId === null) {
            return;
        }

        $selectedVersion->loadMissing('versionSteps.stepVersion');

        /** @var PipelineVersionStep|null $versionStep */
        $versionStep = $selectedVersion->versionSteps
            ->sortBy('position')
            ->first(fn (PipelineVersionStep $item): bool => (int) $item->step_version_id === (int) $this->deletingStepVersionId);

        $stepVersion = $versionStep?->stepVersion;

        if ($versionStep === null || (int) $versionStep->position === 1 || $stepVersion === null) {
            $this->close();

            return;
        }

        $selectedVersionWasCurrent = $this->resolveSelectedVersionIsCurrent();

        $newPipelineVersion = $deletePipelineStepFromVersionAction->handle(
            pipeline: $this->pipeline,
            sourceVersion: $selectedVersion,
            removedStepVersion: $stepVersion,
            setAsCurrent: $selectedVersionWasCurrent,
        );

        $this->dispatch('pipeline-show:pipeline-updated', selectedVersionId: (int) $newPipelineVersion->id);
        $this->close();
    }

    public function render(): View
    {
        return view('pipeline-show.modals.delete-step-alert');
    }
}
