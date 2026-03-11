<?php

namespace App\Services\Pipeline;

use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use Barryvdh\DomPDF\Facade\Pdf;
use League\CommonMark\CommonMarkConverter;

class PipelineStepPdfExporter
{
    public function __construct(private readonly CommonMarkConverter $markdown) {}

    /**
     * @return string binary PDF content
     */
    public function export(PipelineRun $run, PipelineRunStep $step): string
    {
        return $this->exportMarkdown(
            $run->lesson?->name ?? 'Урок',
            (string) ($step->result ?? '')
        );
    }

    /**
     * @return string binary PDF content
     */
    public function exportMarkdown(string $title, string $markdown): string
    {
        $body = $this->markdown->convert($markdown)->getContent();

        return Pdf::loadView('pdf.pipeline-step', [
            'title' => $title,
            'body' => $body,
            'logoPath' => 'uni-logo.png',
        ])
            ->setPaper('a4', 'portrait')
            ->output();
    }
}
