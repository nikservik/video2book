<?php

namespace App\Actions\Project;

use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\Project;
use App\Services\Pipeline\PipelineStepPdfExporter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

class BuildProjectStepResultsArchiveAction
{
    public function __construct(
        private readonly PipelineStepPdfExporter $pipelineStepPdfExporter,
    ) {}

    /**
     * @return array{archive_path:string,cleanup_dir:string,download_filename:string,content_type:string}
     */
    public function handle(Project $project, int $pipelineVersionId, int $stepVersionId, string $format): array
    {
        if (! in_array($format, ['pdf', 'md'], true)) {
            throw new RuntimeException('Неподдерживаемый формат архива.');
        }

        $lessons = $project->lessons()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'name']);

        $lessonIds = $lessons->pluck('id')->all();

        $latestRunsByLesson = PipelineRun::query()
            ->whereIn('lesson_id', $lessonIds)
            ->where('pipeline_version_id', $pipelineVersionId)
            ->where('status', 'done')
            ->with([
                'lesson:id,name',
                'steps' => fn ($query) => $query
                    ->where('step_version_id', $stepVersionId)
                    ->where('status', 'done')
                    ->whereNotNull('result')
                    ->with('stepVersion:id,name,type')
                    ->orderBy('position')
                    ->orderBy('id'),
            ])
            ->orderByDesc('id')
            ->get()
            ->groupBy('lesson_id')
            ->map(fn ($runs) => $runs->first());

        $entries = $lessons
            ->map(function ($lesson) use ($latestRunsByLesson): ?array {
                $run = $latestRunsByLesson->get($lesson->id);

                if ($run === null) {
                    return null;
                }

                /** @var PipelineRunStep|null $step */
                $step = $run->steps->first();

                if ($step === null || blank($step->result)) {
                    return null;
                }

                return [
                    'lesson_name' => $run->lesson?->name ?? $lesson->name,
                    'run' => $run,
                    'step' => $step,
                ];
            })
            ->filter()
            ->values();

        if ($entries->isEmpty()) {
            throw ValidationException::withMessages([
                'projectExportSelection' => 'Для выбранного шага пока нет обработанных результатов в уроках проекта.',
            ]);
        }

        $stepName = $entries->first()['step']->stepVersion?->name ?? 'step';
        $tmpDir = storage_path('app/tmp/project-exports/'.Str::uuid());
        File::ensureDirectoryExists($tmpDir);

        $zipPath = $tmpDir.'/project-export.zip';
        $zip = new ZipArchive;
        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new RuntimeException('Не удалось создать ZIP-архив.');
        }

        $usedNames = [];

        foreach ($entries as $entry) {
            $run = $entry['run'];
            $step = $entry['step'];
            $content = $format === 'pdf'
                ? $this->pipelineStepPdfExporter->export($run, $step)
                : (string) $step->result;

            $baseName = $this->normalizeArchiveBaseName($entry['lesson_name'].'-'.$stepName);

            if ($baseName === '') {
                $baseName = 'lesson_'.$run->lesson_id.'_step_'.$step->step_version_id;
            }

            $extension = $format === 'pdf' ? 'pdf' : 'md';
            $archiveName = $this->resolveUniqueArchiveName($baseName, $extension, $usedNames);
            $filePath = $tmpDir.'/'.$archiveName;

            file_put_contents($filePath, $content);
            $zip->addFile($filePath, $archiveName);
        }

        $zip->close();

        $downloadBaseName = Str::slug($project->name.'-'.$stepName.'-'.$format, '_');

        if ($downloadBaseName === '') {
            $downloadBaseName = 'project-export-'.$format;
        }

        return [
            'archive_path' => $zipPath,
            'cleanup_dir' => $tmpDir,
            'download_filename' => $downloadBaseName.'.zip',
            'content_type' => 'application/zip',
        ];
    }

    /**
     * @param  array<string, bool>  $usedNames
     */
    private function resolveUniqueArchiveName(string $baseName, string $extension, array &$usedNames): string
    {
        $index = 1;
        $candidate = $baseName.'.'.$extension;

        while (isset($usedNames[$candidate])) {
            $index++;
            $candidate = $baseName.'-'.$index.'.'.$extension;
        }

        $usedNames[$candidate] = true;

        return $candidate;
    }

    private function normalizeArchiveBaseName(string $value): string
    {
        $normalized = preg_replace('/[\/\\\\:*?"<>|]+/u', ' ', $value) ?? '';
        $normalized = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $normalized) ?? '';
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? '';
        $normalized = trim($normalized, " .\t\n\r\0\x0B");

        return $normalized;
    }
}
