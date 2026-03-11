<?php

namespace App\Actions\Project;

use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\Project;
use App\Services\Pipeline\PipelineStepDocxExporter;
use App\Services\Pipeline\PipelineStepPdfExporter;
use App\Support\DownloadFilenameSanitizer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class BuildProjectStepResultsArchiveAction
{
    private const ARCHIVE_FILE_NAMING_LESSON = 'lesson';

    private const ARCHIVE_FILE_NAMING_LESSON_STEP = 'lesson_step';

    public function __construct(
        private readonly PipelineStepPdfExporter $pipelineStepPdfExporter,
        private readonly PipelineStepDocxExporter $pipelineStepDocxExporter,
        private readonly DownloadFilenameSanitizer $downloadFilenameSanitizer,
        private readonly GetProjectStepResultEntriesAction $getProjectStepResultEntriesAction,
    ) {}

    /**
     * @return array{archive_path:string,cleanup_dir:string,download_filename:string,content_type:string}
     */
    public function handle(
        Project $project,
        int $pipelineVersionId,
        int $stepVersionId,
        string $format,
        string $archiveFileNaming = self::ARCHIVE_FILE_NAMING_LESSON_STEP,
    ): array {
        if (! in_array($format, ['pdf', 'md', 'docx'], true)) {
            throw new RuntimeException('Неподдерживаемый формат архива.');
        }
        if (! in_array($archiveFileNaming, [self::ARCHIVE_FILE_NAMING_LESSON, self::ARCHIVE_FILE_NAMING_LESSON_STEP], true)) {
            throw new RuntimeException('Неподдерживаемый формат именования файлов в архиве.');
        }

        $entries = $this->getProjectStepResultEntriesAction->handle(
            project: $project,
            pipelineVersionId: $pipelineVersionId,
            stepVersionId: $stepVersionId,
        );

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
            $content = match ($format) {
                'pdf' => $this->pipelineStepPdfExporter->export($run, $step),
                'docx' => $this->pipelineStepDocxExporter->export($run, $step),
                default => (string) $step->result,
            };

            $baseName = $this->resolveArchiveBaseName(
                lessonName: $entry['lesson_name'],
                stepName: $stepName,
                run: $run,
                step: $step,
                archiveFileNaming: $archiveFileNaming,
            );

            if ($baseName === '') {
                $baseName = 'lesson_'.$run->lesson_id;
            }

            $extension = match ($format) {
                'pdf' => 'pdf',
                'docx' => 'docx',
                default => 'md',
            };
            $archiveName = $this->resolveUniqueArchiveName($baseName, $extension, $usedNames);
            $filePath = $tmpDir.'/'.$archiveName;

            file_put_contents($filePath, $content);
            $zip->addFile($filePath, $archiveName);
        }

        $zip->close();

        $downloadBaseName = $this->downloadFilenameSanitizer->sanitize($project->name, 'project-export');

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

    private function resolveArchiveBaseName(
        string $lessonName,
        string $stepName,
        PipelineRun $run,
        PipelineRunStep $step,
        string $archiveFileNaming,
    ): string {
        $baseName = match ($archiveFileNaming) {
            self::ARCHIVE_FILE_NAMING_LESSON => $this->downloadFilenameSanitizer->sanitize($lessonName, ''),
            default => $this->downloadFilenameSanitizer->join([$lessonName, $stepName], ' - ', ''),
        };

        if ($baseName !== '') {
            return $baseName;
        }

        return match ($archiveFileNaming) {
            self::ARCHIVE_FILE_NAMING_LESSON => 'lesson_'.$run->lesson_id,
            default => 'lesson_'.$run->lesson_id.'_step_'.$step->step_version_id,
        };
    }
}
