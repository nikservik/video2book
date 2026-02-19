<?php

namespace App\Services\Pipeline;

use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;

class PipelineStepDocxExporter
{
    private const FONT_NAME = 'Helvetica Neue';

    private const BASE_FONT_STYLE = [
        'name' => self::FONT_NAME,
        'size' => 12,
    ];

    private const BASE_PARAGRAPH_STYLE = [
        'spaceAfter' => 160,
        'lineHeight' => 1.2,
    ];

    private const HEADING_STYLES = [
        1 => [
            'font' => ['name' => self::FONT_NAME, 'size' => 20, 'bold' => true],
            'paragraph' => ['spaceBefore' => 280, 'spaceAfter' => 160, 'lineHeight' => 1.2],
        ],
        2 => [
            'font' => ['name' => self::FONT_NAME, 'size' => 16, 'bold' => true],
            'paragraph' => ['spaceBefore' => 240, 'spaceAfter' => 140, 'lineHeight' => 1.25],
        ],
        3 => [
            'font' => ['name' => self::FONT_NAME, 'size' => 14, 'bold' => true],
            'paragraph' => ['spaceBefore' => 200, 'spaceAfter' => 120, 'lineHeight' => 1.3],
        ],
    ];

    private const BULLET_STYLE = 'v2b-bullet-list';

    private const NUMBER_STYLE = 'v2b-number-list';

    private readonly MarkdownParser $markdownParser;

    public function __construct()
    {
        $environment = new Environment;
        $environment->addExtension(new CommonMarkCoreExtension);

        $this->markdownParser = new MarkdownParser($environment);
    }

    /**
     * @return string binary DOCX content
     */
    public function export(PipelineRun $run, PipelineRunStep $step): string
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName(self::FONT_NAME);
        $phpWord->setDefaultFontSize(12);

        $this->registerListStyles($phpWord);

        $section = $phpWord->addSection([
            'marginTop' => 1000,
            'marginRight' => 1000,
            'marginBottom' => 1000,
            'marginLeft' => 1000,
        ]);

        $document = $this->markdownParser->parse((string) ($step->result ?? ''));

        if ($document instanceof Document) {
            foreach ($document->children() as $blockNode) {
                $this->appendBlockToSection($section, $blockNode);
            }
        }

        return $this->renderDocx($phpWord);
    }

    private function appendBlockToSection(Section $section, Node $blockNode): void
    {
        if ($blockNode instanceof Heading) {
            $this->appendHeading($section, $blockNode);

            return;
        }

        if ($blockNode instanceof Paragraph) {
            $this->appendParagraph($section, $blockNode);

            return;
        }

        if ($blockNode instanceof ListBlock) {
            $this->appendList($section, $blockNode);
        }
    }

    private function appendHeading(Section $section, Heading $heading): void
    {
        $level = $heading->getLevel();
        $resolvedLevel = in_array($level, [1, 2, 3], true) ? $level : 3;
        $style = self::HEADING_STYLES[$resolvedLevel];

        $headingRun = $section->addTextRun($style['paragraph']);
        $this->appendInlineChildren($headingRun, $heading, $style['font']);
    }

    private function appendParagraph(Section $section, Paragraph $paragraph): void
    {
        $paragraphRun = $section->addTextRun(self::BASE_PARAGRAPH_STYLE);
        $this->appendInlineChildren($paragraphRun, $paragraph, self::BASE_FONT_STYLE);
    }

    private function appendList(Section $section, ListBlock $listBlock, int $depth = 0): void
    {
        $listStyle = $listBlock->getListData()->type === ListBlock::TYPE_ORDERED
            ? self::NUMBER_STYLE
            : self::BULLET_STYLE;

        foreach ($listBlock->children() as $itemNode) {
            if (! $itemNode instanceof ListItem) {
                continue;
            }

            $itemRun = $section->addListItemRun(
                $depth,
                $listStyle,
                ['spaceAfter' => 80, 'lineHeight' => 1.2]
            );

            $hasTextContent = false;

            foreach ($itemNode->children() as $itemChildNode) {
                if ($itemChildNode instanceof ListBlock) {
                    $this->appendList($section, $itemChildNode, $depth + 1);

                    continue;
                }

                if ($hasTextContent) {
                    $itemRun->addTextBreak();
                }

                $this->appendListItemChild($itemRun, $itemChildNode);
                $hasTextContent = true;
            }
        }
    }

    private function appendListItemChild(ListItemRun $itemRun, Node $itemChildNode): void
    {
        if ($itemChildNode instanceof Paragraph || $itemChildNode instanceof Heading) {
            $this->appendInlineChildren($itemRun, $itemChildNode, self::BASE_FONT_STYLE);

            return;
        }

        if ($itemChildNode->hasChildren()) {
            $this->appendInlineChildren($itemRun, $itemChildNode, self::BASE_FONT_STYLE);
        }
    }

    /**
     * @param  array<string, mixed>  $fontStyle
     */
    private function appendInlineChildren(TextRun|ListItemRun $run, Node $parentNode, array $fontStyle): void
    {
        foreach ($parentNode->children() as $inlineNode) {
            $this->appendInlineNode($run, $inlineNode, $fontStyle);
        }
    }

    /**
     * @param  array<string, mixed>  $fontStyle
     */
    private function appendInlineNode(TextRun|ListItemRun $run, Node $inlineNode, array $fontStyle): void
    {
        if ($inlineNode instanceof Text) {
            $run->addText($inlineNode->getLiteral(), $fontStyle);

            return;
        }

        if ($inlineNode instanceof Strong) {
            $this->appendInlineChildren($run, $inlineNode, array_merge($fontStyle, ['bold' => true]));

            return;
        }

        if ($inlineNode instanceof Emphasis) {
            $this->appendInlineChildren($run, $inlineNode, array_merge($fontStyle, ['italic' => true]));

            return;
        }

        if ($inlineNode instanceof Code) {
            $run->addText($inlineNode->getLiteral(), array_merge($fontStyle, ['name' => 'Courier New']));

            return;
        }

        if ($inlineNode instanceof Newline) {
            if ($inlineNode->getType() === Newline::HARDBREAK) {
                $run->addTextBreak();
            } else {
                $run->addText(' ', $fontStyle);
            }

            return;
        }

        if ($inlineNode->hasChildren()) {
            $this->appendInlineChildren($run, $inlineNode, $fontStyle);
        }
    }

    private function registerListStyles(PhpWord $phpWord): void
    {
        $bulletLevels = [];
        $numberLevels = [];
        $bulletChars = ['•', '◦', '▪'];

        for ($level = 0; $level < 9; $level++) {
            $left = 720 + ($level * 360);

            $bulletLevels[] = [
                'format' => 'bullet',
                'text' => $bulletChars[$level % count($bulletChars)],
                'left' => $left,
                'hanging' => 240,
                'tabPos' => $left,
                'font' => self::FONT_NAME,
            ];

            $numberLevels[] = [
                'format' => 'decimal',
                'text' => '%'.($level + 1).'.',
                'left' => $left,
                'hanging' => 240,
                'tabPos' => $left,
            ];
        }

        $phpWord->addNumberingStyle(self::BULLET_STYLE, [
            'type' => 'multilevel',
            'levels' => $bulletLevels,
        ]);

        $phpWord->addNumberingStyle(self::NUMBER_STYLE, [
            'type' => 'multilevel',
            'levels' => $numberLevels,
        ]);
    }

    private function renderDocx(PhpWord $phpWord): string
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'v2b-docx-');

        if ($temporaryFile === false) {
            throw new RuntimeException('Не удалось создать временный файл для DOCX-экспорта.');
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($temporaryFile);

        $content = file_get_contents($temporaryFile);
        @unlink($temporaryFile);

        if ($content === false) {
            throw new RuntimeException('Не удалось сформировать DOCX-экспорт.');
        }

        return $content;
    }
}
