<?php

namespace App\Livewire\PipelineShow\Modals;

use App\Actions\Pipeline\DuplicatePipelineFromVersionAction;
use App\Livewire\PipelineShow\Modals\Concerns\ResolvesPipelineVersionData;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class DuplicatePipelineModal extends Component
{
    use ResolvesPipelineVersionData;

    public bool $show = false;

    public string $copyPipelineTitle = '';

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

    #[On('pipeline-show:duplicate-pipeline-modal-open')]
    public function open(): void
    {
        $this->refreshPipelineData();

        if ($this->resolveSelectedVersion() === null) {
            return;
        }

        $this->resetErrorBag();
        $this->copyPipelineTitle = '';

        $this->dispatch('pipeline-show:close-modals', except: 'duplicate-pipeline');
        $this->show = true;
    }

    #[On('pipeline-show:close-modals')]
    public function closeByEvent(?string $except = null): void
    {
        if ($except === 'duplicate-pipeline') {
            return;
        }

        $this->close();
    }

    public function close(): void
    {
        $this->show = false;
        $this->copyPipelineTitle = '';
    }

    public function create(DuplicatePipelineFromVersionAction $duplicatePipelineFromVersionAction)
    {
        $selectedVersion = $this->resolveSelectedVersion();

        if (! $this->show || $selectedVersion === null) {
            return null;
        }

        $validated = validator([
            'copyPipelineTitle' => $this->copyPipelineTitle,
        ], [
            'copyPipelineTitle' => ['required', 'string', 'max:255'],
        ], [], [
            'copyPipelineTitle' => 'название копии шаблона',
        ])->validate();

        $pipeline = $duplicatePipelineFromVersionAction->handle(
            sourceVersion: $selectedVersion,
            title: $validated['copyPipelineTitle'],
        );

        $this->close();

        return $this->redirectRoute('pipelines.show', ['pipeline' => $pipeline], navigate: true);
    }

    public function render(): View
    {
        return view('pipeline-show.modals.duplicate-pipeline-modal');
    }
}
