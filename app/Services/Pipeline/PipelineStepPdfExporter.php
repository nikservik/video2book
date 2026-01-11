<?php

namespace App\Services\Pipeline;

use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;
use TCPDF;

class PipelineStepPdfExporter
{
    public function __construct(private readonly CommonMarkConverter $markdown)
    {
    }

    /**
     * @return string binary PDF content
     */
    public function export(PipelineRun $run, PipelineRunStep $step): string
    {
        $lessonName = $run->lesson?->name ?? 'Урок';
        $stepName = $step->stepVersion?->name ?? 'Шаг';
        $title = sprintf('%s — %s', $lessonName, $stepName);

        $html = $this->renderHtml($title, $step->result ?? '');

        $pdf = new TCPDF();
        $pdf->SetTitle($title);
        $pdf->SetAuthor(config('app.name', 'Video2Book'));
        $pdf->SetMargins(15, 20, 15);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output(Str::random(8).'.pdf', 'S');
    }

    private function renderHtml(string $title, string $markdown): string
    {
        $body = $this->markdown->convert($markdown)->getContent();

        return <<<HTML
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
    h1, h2, h3, h4 { color: #111827; margin: 0 0 8px; }
    h1 { font-size: 20px; }
    h2 { font-size: 16px; }
    h3 { font-size: 14px; }
    h4 { font-size: 13px; }
    p { margin: 0 0 8px; line-height: 1.5; }
    ul, ol { margin: 0 0 10px 18px; padding: 0; }
    li { margin-bottom: 4px; }
    strong { font-weight: 700; }
    em { font-style: italic; }
    u { text-decoration: underline; }
  </style>
</head>
<body>
  <h1>{$this->escape($title)}</h1>
  {$body}
</body>
</html>
HTML;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
