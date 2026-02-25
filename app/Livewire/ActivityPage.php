<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesAccessLevel;
use App\Models\Lesson;
use App\Models\PipelineRun;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

class ActivityPage extends Component
{
    use AuthorizesAccessLevel;
    use WithPagination;

    private const PER_PAGE = 20;

    public function mount(): void
    {
        $this->authorizeAccessLevel(User::ACCESS_LEVEL_ADMIN);
    }

    public function render(): View
    {
        $activities = Activity::query()
            ->whereIn('event', ['created', 'updated', 'deleted'])
            ->whereIn('subject_type', [Project::class, Lesson::class, PipelineRun::class])
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        $missingCauserUserIds = $activities->getCollection()
            ->filter(fn (Activity $activity): bool => $activity->causer_type === User::class && $activity->causer_id !== null)
            ->map(fn (Activity $activity): int => (int) $activity->causer_id)
            ->values()
            ->all();

        $causerNamesById = User::withTrashed()
            ->whereIn('id', $missingCauserUserIds)
            ->pluck('name', 'id')
            ->map(fn (mixed $name): string => (string) $name)
            ->all();

        $pipelineRunIds = $activities->getCollection()
            ->filter(fn (Activity $activity): bool => $activity->subject_type === PipelineRun::class && $activity->subject_id !== null)
            ->map(fn (Activity $activity): int => (int) $activity->subject_id)
            ->values()
            ->all();

        $pipelineRunNamesById = PipelineRun::withTrashed()
            ->whereIn('id', $pipelineRunIds)
            ->with([
                'lesson' => fn ($query) => $query->withTrashed(),
                'pipelineVersion',
            ])
            ->get()
            ->mapWithKeys(fn (PipelineRun $pipelineRun): array => [
                (int) $pipelineRun->id => $this->formatPipelineRunName($pipelineRun),
            ])
            ->all();

        $lessonIds = $activities->getCollection()
            ->filter(fn (Activity $activity): bool => $activity->subject_type === Lesson::class && $activity->subject_id !== null)
            ->map(fn (Activity $activity): int => (int) $activity->subject_id)
            ->values()
            ->all();

        $lessonNamesById = Lesson::withTrashed()
            ->whereIn('id', $lessonIds)
            ->with([
                'project' => fn ($query) => $query->withTrashed(),
            ])
            ->get()
            ->mapWithKeys(fn (Lesson $lesson): array => [
                (int) $lesson->id => $this->formatLessonName($lesson),
            ])
            ->all();

        $projectIds = $activities->getCollection()
            ->filter(fn (Activity $activity): bool => $activity->subject_type === Project::class && $activity->subject_id !== null)
            ->map(fn (Activity $activity): int => (int) $activity->subject_id)
            ->values()
            ->all();

        $projectNamesById = Project::withTrashed()
            ->whereIn('id', $projectIds)
            ->pluck('name', 'id')
            ->map(fn (mixed $name): string => (string) $name)
            ->all();

        $activities->setCollection(
            $activities->getCollection()->map(function (Activity $activity) use ($causerNamesById, $pipelineRunNamesById, $lessonNamesById, $projectNamesById): array {
                return [
                    'id' => (int) $activity->id,
                    'dateTime' => $activity->created_at?->format('d.m.Y H:i') ?? '—',
                    'customDescription' => $this->resolveCustomDescription($activity),
                    'userName' => $this->resolveCauserName($activity, $causerNamesById),
                    'action' => $this->resolveActionLabel($activity->event),
                    'subjectTypeLabel' => $this->resolveSubjectTypeLabel($activity->subject_type),
                    'subjectName' => $this->resolveSubjectName($activity, $pipelineRunNamesById, $lessonNamesById, $projectNamesById),
                ];
            })
        );

        return view('pages.activity-page', [
            'activities' => $activities,
        ])->layout('layouts.app', [
            'title' => 'Активность | '.config('app.name', 'Video2Book'),
            'breadcrumbs' => [
                ['label' => 'Активность', 'current' => true],
            ],
        ]);
    }

    /**
     * @param  array<int, string>  $causerNamesById
     */
    private function resolveCauserName(Activity $activity, array $causerNamesById): string
    {
        $causerId = $activity->causer_id === null ? null : (int) $activity->causer_id;

        if ($activity->causer_type === User::class && $causerId !== null) {
            return $causerNamesById[$causerId] ?? "Пользователь #{$causerId}";
        }

        return 'Система';
    }

    private function resolveActionLabel(?string $event): string
    {
        return match ($event) {
            'created' => 'добавил(а)',
            'updated' => 'изменил(а)',
            'deleted' => 'удалил(а)',
            default => 'действие',
        };
    }

    private function resolveCustomDescription(Activity $activity): ?string
    {
        $context = (string) data_get($activity->properties, 'context', '');

        if ($context !== 'pipeline-run-step-result-edited') {
            return null;
        }

        $description = trim((string) $activity->description);

        return $description !== '' ? $description : null;
    }

    private function resolveSubjectTypeLabel(?string $subjectType): string
    {
        return match ($subjectType) {
            Project::class => 'проект',
            Lesson::class => 'урок',
            PipelineRun::class => 'прогон',
            default => 'объект',
        };
    }

    /**
     * @param  array<int, string>  $pipelineRunNamesById
     * @param  array<int, string>  $lessonNamesById
     * @param  array<int, string>  $projectNamesById
     */
    private function resolveSubjectName(Activity $activity, array $pipelineRunNamesById, array $lessonNamesById, array $projectNamesById): string
    {
        $subjectId = $activity->subject_id === null ? null : (int) $activity->subject_id;
        $attributes = $activity->properties['attributes'] ?? [];
        $old = $activity->properties['old'] ?? [];

        return match ($activity->subject_type) {
            Project::class => $subjectId === null
                ? 'Проект'
                : (string) ($attributes['name'] ?? $old['name'] ?? ($projectNamesById[$subjectId] ?? "Проект #{$subjectId}")),
            Lesson::class => $subjectId === null
                ? 'Урок'
                : ($lessonNamesById[$subjectId] ?? ($attributes['name'] ?? $old['name'] ?? "Урок #{$subjectId}")),
            PipelineRun::class => $subjectId === null
                ? 'Прогон'
                : ($pipelineRunNamesById[$subjectId] ?? "Прогон #{$subjectId}"),
            default => $subjectId === null ? 'Объект' : "Объект #{$subjectId}",
        };
    }

    private function formatPipelineRunName(PipelineRun $pipelineRun): string
    {
        $lessonName = $pipelineRun->lesson?->name;
        $pipelineTitle = $pipelineRun->pipelineVersion?->title;
        $pipelineVersion = $pipelineRun->pipelineVersion?->version;

        if ($lessonName !== null && $pipelineTitle !== null && $pipelineVersion !== null) {
            return "{$lessonName} — {$pipelineTitle} • v{$pipelineVersion}";
        }

        return "Прогон #{$pipelineRun->id}";
    }

    private function formatLessonName(Lesson $lesson): string
    {
        $lessonName = $lesson->name;
        $projectName = $lesson->project?->name;

        if ($lessonName !== null && $projectName !== null) {
            return "{$lessonName}» в проекте «{$projectName}";
        }

        return "Урок #{$lesson->id}";
    }
}
