<?php

namespace Tests\Unit\Pipeline;

use App\Models\Lesson;
use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\StepVersion;
use App\Services\Pipeline\PipelineStepDocxExporter;
use Tests\TestCase;
use ZipArchive;

class PipelineStepDocxExporterTest extends TestCase
{
    public function test_it_exports_markdown_to_docx_with_headings_lists_and_inline_styles(): void
    {
        $run = new PipelineRun;
        $run->setRelation('lesson', new Lesson(['name' => 'Урок']));

        $step = new PipelineRunStep([
            'result' => <<<'MD'
# Заголовок H1

## Заголовок H2

### Заголовок H3

- **Жирный** пункт
  - *Вложенный* пункт

1. Первый
   1. Второй уровень
MD,
        ]);
        $step->setRelation('stepVersion', new StepVersion(['name' => 'Результат']));

        $content = app(PipelineStepDocxExporter::class)->export($run, $step);

        $this->assertStringStartsWith('PK', $content);

        $temporaryPath = tempnam(sys_get_temp_dir(), 'v2b-docx-test-');
        $this->assertNotFalse($temporaryPath);

        file_put_contents($temporaryPath, $content);

        $zip = new ZipArchive;
        $openResult = $zip->open($temporaryPath);
        $this->assertSame(true, $openResult);

        $documentXml = (string) $zip->getFromName('word/document.xml');
        $stylesXml = (string) $zip->getFromName('word/styles.xml');

        $zip->close();
        @unlink($temporaryPath);

        $this->assertStringContainsString('Заголовок H1', $documentXml);
        $this->assertStringContainsString('Заголовок H2', $documentXml);
        $this->assertStringContainsString('Заголовок H3', $documentXml);
        $this->assertStringNotContainsString('Урок — Результат', $documentXml);
        $this->assertStringContainsString('<w:b', $documentXml);
        $this->assertStringContainsString('<w:i', $documentXml);
        $this->assertStringContainsString('w:ilvl w:val="1"', $documentXml);
        $this->assertStringContainsString('w:before="', $documentXml);
        $this->assertStringContainsString('Helvetica Neue', $stylesXml);
        $this->assertStringContainsString('w:sz w:val="24"', $stylesXml);
    }
}
