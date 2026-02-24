<?php

namespace App\Livewire;

use App\Actions\Pipeline\CreatePipelineWithStepsAction;
use App\Livewire\Concerns\AuthorizesAccessLevel;
use App\Models\User;
use App\Services\Pipeline\PaginatedPipelinesQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class PipelinesPage extends Component
{
    use AuthorizesAccessLevel;

    private const PER_PAGE = 15;

    public bool $showCreatePipelineModal = false;

    public string $createPipelineTitle = '';

    public string $createPipelineDescription = '';

    /**
     * @var array<int, string>
     */
    public array $createPipelineStepNames = [];

    public function mount(): void
    {
        $this->authorizeAccessLevel(User::ACCESS_LEVEL_ADMIN);
    }

    public function openCreatePipelineModal(): void
    {
        $this->resetErrorBag();
        $this->createPipelineTitle = '';
        $this->createPipelineDescription = '';
        $this->createPipelineStepNames = ['Транскрибация', 'Структура'];

        $this->showCreatePipelineModal = true;
    }

    public function closeCreatePipelineModal(): void
    {
        $this->showCreatePipelineModal = false;
    }

    /**
     * @throws ValidationException
     */
    public function savePipeline(string $title, ?string $description, array $stepNames): void
    {
        $this->createPipelineTitle = $title;
        $this->createPipelineDescription = $description ?? '';
        $this->createPipelineStepNames = $stepNames;

        $normalizedStepNames = collect($stepNames)
            ->map(fn (mixed $stepName): string => trim((string) $stepName))
            ->values()
            ->all();

        $validated = validator([
            'createPipelineTitle' => trim($title),
            'createPipelineDescription' => blank($description) ? null : trim((string) $description),
            'stepNames' => $normalizedStepNames,
        ], [
            'createPipelineTitle' => ['required', 'string', 'max:255'],
            'createPipelineDescription' => ['nullable', 'string'],
            'stepNames' => ['required', 'array', 'min:1'],
            'stepNames.*' => ['required', 'string', 'max:255'],
        ], [], [
            'createPipelineTitle' => 'название шаблона',
            'createPipelineDescription' => 'описание шаблона',
            'stepNames' => 'список шагов',
            'stepNames.*' => 'название шага',
        ])->validate();

        $pipeline = app(CreatePipelineWithStepsAction::class)->handle(
            title: $validated['createPipelineTitle'],
            description: $validated['createPipelineDescription'],
            stepNames: $validated['stepNames'],
        );

        $this->redirectRoute('pipelines.show', ['pipeline' => $pipeline], navigate: true);
    }

    public function render(): View
    {
        return view('pages.pipelines-page', [
            'pipelines' => app(PaginatedPipelinesQuery::class)->get(self::PER_PAGE),
        ])->layout('layouts.app', [
            'title' => 'Шаблоны | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [
                ['label' => 'Шаблоны', 'current' => true],
            ],
        ]);
    }
}
