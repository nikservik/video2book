<?php

namespace App\Livewire\PipelineShow\Modals;

use App\Livewire\PipelineShow\Modals\Concerns\ResolvesPipelineVersionData;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class ChangelogModal extends Component
{
    use ResolvesPipelineVersionData;

    public bool $show = false;

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

    #[On('pipeline-show:changelog-modal-open')]
    public function open(): void
    {
        $this->refreshPipelineData();

        if ($this->resolveSelectedVersion() === null) {
            return;
        }

        $this->dispatch('pipeline-show:close-modals', except: 'changelog');
        $this->show = true;
    }

    #[On('pipeline-show:close-modals')]
    public function closeByEvent(?string $except = null): void
    {
        if ($except === 'changelog') {
            return;
        }

        $this->close();
    }

    public function close(): void
    {
        $this->show = false;
    }

    public function getSelectedVersionNumberProperty(): string
    {
        return (string) ($this->resolveSelectedVersion()?->version ?? '—');
    }

    public function getSelectedVersionChangelogProperty(): string
    {
        $changelog = trim((string) ($this->resolveSelectedVersion()?->changelog ?? ''));

        if ($changelog === '') {
            return 'Для этой версии changelog пока пуст.';
        }

        return $changelog;
    }

    public function render(): View
    {
        return view('pipeline-show.modals.changelog-modal');
    }
}
