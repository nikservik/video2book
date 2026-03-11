<?php

namespace App\Actions\Project;

use App\Models\Project;
use App\Services\Pipeline\PipelineStepDocxExporter;
use App\Services\Pipeline\PipelineStepPdfExporter;
use App\Support\DownloadFilenameSanitizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class BuildProjectStepResultsSingleFileAction
{
    public function __construct(
        private readonly GetProjectStepResultEntriesAction $getProjectStepResultEntriesAction,
        private readonly PipelineStepPdfExporter $pipelineStepPdfExporter,
        private readonly PipelineStepDocxExporter $pipelineStepDocxExporter,
        private readonly DownloadFilenameSanitizer $downloadFilenameSanitizer,
    ) {}

    /**
     * @return array{file_path:string,cleanup_dir:string,download_filename:string,content_type:string}
     */
    public function handle(
        Project $project,
        int $pipelineVersionId,
        int $stepVersionId,
        string $format,
    ): array {
        if (! in_array($format, ['pdf', 'md', 'docx'], true)) {
            throw new RuntimeException('Неподдерживаемый формат файла.');
        }

        $entries = $this->getProjectStepResultEntriesAction->handle(
            project: $project,
            pipelineVersionId: $pipelineVersionId,
            stepVersionId: $stepVersionId,
        );

        $combinedMarkdown = $this->buildCombinedMarkdown($entries);
        $downloadBaseName = $this->downloadFilenameSanitizer->sanitize($project->name, 'project-export');
        $tmpDir = storage_path('app/tmp/project-exports/'.Str::uuid());

        File::ensureDirectoryExists($tmpDir);

        [$content, $extension, $contentType] = match ($format) {
            'pdf' => [
                $this->pipelineStepPdfExporter->exportMarkdown($project->name, $combinedMarkdown),
                'pdf',
                'application/pdf',
            ],
            'docx' => [
                $this->pipelineStepDocxExporter->exportMarkdown($combinedMarkdown),
                'docx',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            default => [
                $combinedMarkdown,
                'md',
                'text/markdown; charset=UTF-8',
            ],
        };

        $filePath = $tmpDir.'/'.$downloadBaseName.'.'.$extension;
        file_put_contents($filePath, $content);

        return [
            'file_path' => $filePath,
            'cleanup_dir' => $tmpDir,
            'download_filename' => $downloadBaseName.'.'.$extension,
            'content_type' => $contentType,
        ];
    }

    /**
     * @param  Collection<int, array{lesson_name:string, run:\App\Models\PipelineRun, step:\App\Models\PipelineRunStep}>  $entries
     */
    private function buildCombinedMarkdown(Collection $entries): string
    {
        return $entries
            ->map(function (array $entry): string {
                $lessonName = $this->normalizeLessonHeading((string) $entry['lesson_name']);
                $result = $this->shiftMarkdownHeadingLevels((string) $entry['step']->result);
                $parts = array_filter([
                    '# '.$lessonName,
                    trim($result),
                ], fn (string $value): bool => $value !== '');

                return implode("\n\n", $parts);
            })
            ->implode("\n\n");
    }

    private function normalizeLessonHeading(string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', str_replace(["\r", "\n"], ' ', $value)) ?? '';
        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : 'Урок';
    }

    private function shiftMarkdownHeadingLevels(string $markdown): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $markdown) ?: [];
        $fenceMarker = null;

        foreach ($lines as $index => $line) {
            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line, $matches) === 1) {
                $marker = $matches[1][0];
                $fenceMarker = $fenceMarker === null ? $marker : ($fenceMarker === $marker ? null : $fenceMarker);

                continue;
            }

            if ($fenceMarker !== null) {
                continue;
            }

            if (preg_match('/^(#{1,6})([ \t].*)$/u', $line, $matches) !== 1) {
                continue;
            }

            $level = min(strlen($matches[1]) + 1, 6);
            $lines[$index] = str_repeat('#', $level).$matches[2];
        }

        return implode("\n", $lines);
    }
}
