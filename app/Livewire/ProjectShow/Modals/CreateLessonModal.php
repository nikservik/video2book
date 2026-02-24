<?php

namespace App\Livewire\ProjectShow\Modals;

use App\Actions\Pipeline\GetPipelineVersionOptionsAction;
use App\Actions\Project\CreateProjectLessonFromYoutubeAction;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class CreateLessonModal extends Component
{
    public int $projectId;

    public bool $show = false;

    public string $newLessonName = '';

    public string $newLessonYoutubeUrl = '';

    public ?int $newLessonPipelineVersionId = null;

    /**
     * @var array<int, array{id:int,label:string,description:string|null}>
     */
    public array $pipelineVersionOptions = [];

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;
        $this->pipelineVersionOptions = app(GetPipelineVersionOptionsAction::class)->handle();
    }

    #[On('project-show:create-lesson-modal-open')]
    public function open(): void
    {
        $this->pipelineVersionOptions = app(GetPipelineVersionOptionsAction::class)->handle();

        $this->resetErrorBag();
        $this->newLessonName = '';
        $this->newLessonYoutubeUrl = '';
        $this->newLessonPipelineVersionId = $this->resolvePreferredPipelineVersionId($this->pipelineVersionOptions);

        if ($this->show) {
            return;
        }

        $this->show = true;
        $this->dispatch('project-show:modal-opened');
    }

    public function close(): void
    {
        if (! $this->show) {
            return;
        }

        $this->show = false;
        $this->dispatch('project-show:modal-closed');
    }

    public function createLessonFromYoutube(CreateProjectLessonFromYoutubeAction $action): void
    {
        $availablePipelineVersionIds = $this->availablePipelineVersionIds();

        $validated = $this->validate([
            'newLessonName' => ['required', 'string', 'max:255'],
            'newLessonYoutubeUrl' => ['required', 'url', 'starts_with:https://'],
            'newLessonPipelineVersionId' => ['required', 'integer', Rule::in($availablePipelineVersionIds)],
        ], [], [
            'newLessonName' => 'название урока',
            'newLessonYoutubeUrl' => 'ссылка на YouTube',
            'newLessonPipelineVersionId' => 'версия шаблона',
        ]);

        $action->handle(
            $this->project(),
            $validated['newLessonName'],
            $validated['newLessonYoutubeUrl'],
            (int) $validated['newLessonPipelineVersionId'],
        );

        $this->dispatch('project-show:project-updated');
        $this->close();
    }

    public function updatedNewLessonPipelineVersionId($value): void
    {
        $this->newLessonPipelineVersionId = $value === '' || $value === null ? null : (int) $value;
    }

    public function getSelectedPipelineVersionLabelProperty(): string
    {
        return data_get(
            collect($this->pipelineVersionOptions)->firstWhere('id', $this->newLessonPipelineVersionId),
            'label',
            'Выберите версию'
        );
    }

    /**
     * @param  array<int, array{id:int,label:string,description:string|null}>  $pipelineVersionOptions
     */
    private function resolvePreferredPipelineVersionId(array $pipelineVersionOptions): ?int
    {
        $defaultPipelineVersionId = $this->project()->default_pipeline_version_id;

        if ($defaultPipelineVersionId !== null) {
            $hasDefaultOption = collect($pipelineVersionOptions)
                ->contains(fn (array $option): bool => $option['id'] === $defaultPipelineVersionId);

            if ($hasDefaultOption) {
                return $defaultPipelineVersionId;
            }
        }

        return $pipelineVersionOptions[0]['id'] ?? null;
    }

    /**
     * @return array<int, int>
     */
    private function availablePipelineVersionIds(): array
    {
        return collect($this->pipelineVersionOptions)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function project(): Project
    {
        return Project::query()->findOrFail($this->projectId);
    }

    public function render(): View
    {
        return view('project-show.modals.create-lesson-modal', [
            'pipelineVersionOptions' => $this->pipelineVersionOptions,
        ]);
    }
}
