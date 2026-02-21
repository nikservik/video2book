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
        $title = $run->lesson?->name ?? 'Урок';
        $body = $this->markdown->convert($step->result ?? '')->getContent();

        return Pdf::loadView('pdf.pipeline-step', [
            'title' => $title,
            'body' => $body,
            'logoPath' => 'uni-logo.png',
        ])
            ->setPaper('a4', 'portrait')
            ->output();
    }
}
