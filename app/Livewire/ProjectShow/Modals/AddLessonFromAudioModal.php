<?php

namespace App\Livewire\ProjectShow\Modals;

use App\Actions\Pipeline\GetPipelineVersionOptionsAction;
use App\Actions\Project\CreateProjectLessonFromAudioAction;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class AddLessonFromAudioModal extends Component
{
    use WithFileUploads;

    public int $projectId;

    public bool $show = false;

    public string $newLessonName = '';

    public ?int $newLessonPipelineVersionId = null;

    /**
     * @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null
     */
    public $newLessonAudioFile = null;

    /**
     * @var array<int, array{id:int,label:string}>
     */
    public array $pipelineVersionOptions = [];

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;
        $this->pipelineVersionOptions = app(GetPipelineVersionOptionsAction::class)->handle();
    }

    #[On('project-show:add-lesson-from-audio-modal-open')]
    public function open(): void
    {
        $this->pipelineVersionOptions = app(GetPipelineVersionOptionsAction::class)->handle();

        $this->resetErrorBag();
        $this->newLessonName = '';
        $this->newLessonAudioFile = null;
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
        $this->dispatch('project-show:audio-upload-finished');
        $this->dispatch('project-show:modal-closed');
    }

    public function createLessonFromAudio(CreateProjectLessonFromAudioAction $action): void
    {
        $availablePipelineVersionIds = $this->availablePipelineVersionIds();

        $validated = $this->validate([
            'newLessonName' => ['required', 'string', 'max:255'],
            'newLessonAudioFile' => [
                'required',
                'file',
                'max:512000',
                'mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/mp4,audio/x-m4a,audio/aac,audio/ogg,audio/webm,audio/flac',
            ],
            'newLessonPipelineVersionId' => ['required', 'integer', Rule::in($availablePipelineVersionIds)],
        ], [], [
            'newLessonName' => 'название урока',
            'newLessonAudioFile' => 'аудиофайл',
            'newLessonPipelineVersionId' => 'версия пайплайна',
        ]);

        $action->handle(
            $this->project(),
            $validated['newLessonName'],
            $validated['newLessonAudioFile'],
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

    public function getSelectedAudioFilenameProperty(): ?string
    {
        if ($this->newLessonAudioFile === null) {
            return null;
        }

        return trim((string) $this->newLessonAudioFile->getClientOriginalName()) ?: null;
    }

    /**
     * @param  array<int, array{id:int,label:string}>  $pipelineVersionOptions
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
        return view('project-show.modals.add-lesson-from-audio-modal', [
            'pipelineVersionOptions' => $this->pipelineVersionOptions,
        ]);
    }
}
