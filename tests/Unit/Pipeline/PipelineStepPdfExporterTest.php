<?php

namespace Tests\Unit\Pipeline;

use App\Models\Lesson;
use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Services\Pipeline\PipelineStepPdfExporter;
use Tests\TestCase;

class PipelineStepPdfExporterTest extends TestCase
{
    public function test_it_exports_markdown_to_pdf_binary(): void
    {
        $run = new PipelineRun;
        $run->setRelation('lesson', new Lesson(['name' => 'Урок']));

        $step = new PipelineRunStep([
            'result' => <<<'MD'
# Заголовок

Текст абзаца с **жирным** и *курсивом*.

- Пункт 1
- Пункт 2
MD,
        ]);

        $content = app(PipelineStepPdfExporter::class)->export($run, $step);

        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertGreaterThan(1024, strlen($content));
    }
}
