<?php

namespace App\Livewire\ProjectShow\Modals;

use App\Actions\Project\BuildProjectStepResultsArchiveAction;
use App\Actions\Project\GetProjectExportPipelineStepOptionsAction;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class ProjectExportModal extends Component
{
    public int $projectId;

    public bool $show = false;

    public string $projectExportFormat = 'pdf';

    public ?string $projectExportSelection = null;

    /**
     * @var array<int, array{id:int,label:string,steps:array<int, array{id:int,name:string}>}>
     */
    public array $projectExportPipelineOptions = [];

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;
    }

    #[On('project-show:project-export-modal-open')]
    public function open(string $format): void
    {
        if (! in_array($format, ['pdf', 'md'], true)) {
            return;
        }

        $this->resetErrorBag();

        $this->projectExportFormat = $format;
        $this->projectExportPipelineOptions = app(GetProjectExportPipelineStepOptionsAction::class)->handle($this->project());
        $this->projectExportSelection = $this->resolvePreferredProjectExportSelection($this->projectExportPipelineOptions);

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
        $this->projectExportSelection = null;
        $this->projectExportPipelineOptions = [];

        $this->dispatch('project-show:modal-closed');
    }

    public function downloadProjectResults(BuildProjectStepResultsArchiveAction $buildProjectStepResultsArchiveAction)
    {
        $availableSelections = $this->availableProjectExportSelections();

        $validated = validator([
            'projectExportSelection' => $this->projectExportSelection,
        ], [
            'projectExportSelection' => ['required', 'string', Rule::in($availableSelections)],
        ], [], [
            'projectExportSelection' => 'шаг для скачивания',
        ])->validate();

        [$pipelineVersionId, $stepVersionId] = $this->parseProjectExportSelection($validated['projectExportSelection']);

        abort_if($pipelineVersionId === null || $stepVersionId === null, 422, 'Невалидный выбор шага для скачивания.');

        try {
            $archive = $buildProjectStepResultsArchiveAction->handle(
                project: $this->project(),
                pipelineVersionId: $pipelineVersionId,
                stepVersionId: $stepVersionId,
                format: $this->projectExportFormat,
            );
        } catch (ValidationException $exception) {
            $this->addError(
                'projectExportSelection',
                $exception->errors()['projectExportSelection'][0] ?? 'Не удалось подготовить результаты для скачивания.'
            );

            return null;
        }

        $this->close();

        return response()->streamDownload(function () use ($archive): void {
            $stream = fopen($archive['archive_path'], 'rb');

            if ($stream !== false) {
                fpassthru($stream);
                fclose($stream);
            }

            File::deleteDirectory($archive['cleanup_dir']);
        }, $archive['download_filename'], [
            'Content-Type' => $archive['content_type'],
        ]);
    }

    public function getProjectExportTitleProperty(): string
    {
        return sprintf('Скачивание проекта в %s', Str::upper($this->projectExportFormat));
    }

    public function isProjectExportPipelineExpanded(int $pipelineVersionId): bool
    {
        [$selectedPipelineVersionId] = $this->parseProjectExportSelection($this->projectExportSelection);

        return $selectedPipelineVersionId === $pipelineVersionId;
    }

    /**
     * @param  array<int, array{id:int,label:string,steps:array<int, array{id:int,name:string}>}>  $pipelineOptions
     */
    private function resolvePreferredProjectExportSelection(array $pipelineOptions): ?string
    {
        $defaultPipelineVersionId = $this->project()->default_pipeline_version_id;

        if ($defaultPipelineVersionId !== null) {
            $defaultPipeline = collect($pipelineOptions)->firstWhere('id', $defaultPipelineVersionId);

            if (is_array($defaultPipeline) && data_get($defaultPipeline, 'steps.0.id') !== null) {
                return $defaultPipeline['id'].':'.$defaultPipeline['steps'][0]['id'];
            }
        }

        $firstPipelineId = data_get($pipelineOptions, '0.id');
        $firstStepId = data_get($pipelineOptions, '0.steps.0.id');

        if ($firstPipelineId === null || $firstStepId === null) {
            return null;
        }

        return $firstPipelineId.':'.$firstStepId;
    }

    /**
     * @return array<int, string>
     */
    private function availableProjectExportSelections(): array
    {
        return collect($this->projectExportPipelineOptions)
            ->flatMap(fn (array $pipeline) => collect($pipeline['steps'])
                ->map(fn (array $step): string => $pipeline['id'].':'.$step['id']))
            ->values()
            ->all();
    }

    /**
     * @return array{0:?int,1:?int}
     */
    private function parseProjectExportSelection(?string $selection): array
    {
        if ($selection === null || ! preg_match('/^\d+:\d+$/', $selection)) {
            return [null, null];
        }

        [$pipelineVersionId, $stepVersionId] = explode(':', $selection, 2);

        return [(int) $pipelineVersionId, (int) $stepVersionId];
    }

    private function project(): Project
    {
        return Project::query()->findOrFail($this->projectId);
    }

    public function render(): View
    {
        return view('project-show.modals.project-export-modal');
    }
}
