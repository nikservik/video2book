<?php

namespace App\Livewire\PipelineShow\Modals;

use App\Actions\Pipeline\UpdatePipelineVersionAction;
use App\Livewire\PipelineShow\Modals\Concerns\ResolvesPipelineVersionData;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class EditVersionModal extends Component
{
    use ResolvesPipelineVersionData;

    public bool $show = false;

    public string $editableVersionTitle = '';

    public string $editableVersionDescription = '';

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

    #[On('pipeline-show:edit-version-modal-open')]
    public function open(): void
    {
        $this->refreshPipelineData();

        $selectedVersion = $this->resolveSelectedVersion();

        if ($selectedVersion === null) {
            return;
        }

        $this->resetErrorBag();
        $this->editableVersionTitle = (string) $selectedVersion->title;
        $this->editableVersionDescription = (string) ($selectedVersion->description ?? '');

        $this->dispatch('pipeline-show:close-modals', except: 'edit-version');
        $this->show = true;
    }

    #[On('pipeline-show:close-modals')]
    public function closeByEvent(?string $except = null): void
    {
        if ($except === 'edit-version') {
            return;
        }

        $this->close();
    }

    public function close(): void
    {
        $this->show = false;
    }

    public function saveVersion(UpdatePipelineVersionAction $updatePipelineVersionAction): void
    {
        $selectedVersion = $this->resolveSelectedVersion();

        if (! $this->show || $selectedVersion === null) {
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

        $this->dispatch('pipeline-show:pipeline-updated', selectedVersionId: (int) $updatedVersion->id);
        $this->close();
    }

    public function render(): View
    {
        return view('pipeline-show.modals.edit-version-modal');
    }
}
