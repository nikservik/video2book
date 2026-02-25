<?php

namespace App\Support;

use DOMElement;
use DOMNode;
use DOMNodeList;

class StepResultHtmlToMarkdownConverter
{
    public function convert(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new \DOMDocument;
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        /** @var DOMElement|null $body */
        $body = $document->getElementsByTagName('body')->item(0);

        if ($body === null) {
            return '';
        }

        $blocks = $this->convertBlockNodes($body->childNodes);

        return trim(implode("\n\n", $blocks));
    }

    /**
     * @return list<string>
     */
    private function convertBlockNodes(DOMNodeList $nodes): array
    {
        $blocks = [];
        $inlineBuffer = '';

        foreach ($nodes as $node) {
            if ($this->shouldAccumulateAsInlineBlockNode($node)) {
                $inlineBuffer .= $this->convertInlineNode($node);

                continue;
            }

            $inlineText = $this->cleanInline($inlineBuffer);
            if ($inlineText !== '') {
                $blocks[] = $inlineText;
            }
            $inlineBuffer = '';

            foreach ($this->convertBlockNode($node) as $block) {
                $normalizedBlock = trim($block);

                if ($normalizedBlock === '') {
                    continue;
                }

                $blocks[] = $normalizedBlock;
            }
        }

        $inlineText = $this->cleanInline($inlineBuffer);
        if ($inlineText !== '') {
            $blocks[] = $inlineText;
        }

        return $blocks;
    }

    /**
     * @return list<string>
     */
    private function convertBlockNode(DOMNode $node): array
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = $this->normalizeText((string) $node->nodeValue);

            return $text === '' ? [] : [$text];
        }

        if (! ($node instanceof DOMElement)) {
            return [];
        }

        $tag = strtolower($node->tagName);

        return match ($tag) {
            'h1' => [$this->convertHeader($node, 1)],
            'h2' => [$this->convertHeader($node, 2)],
            'h3' => [$this->convertHeader($node, 3)],
            'ul', 'ol' => [$this->convertList($node, 0)],
            'div', 'p' => $this->convertContainerNode($node),
            default => $this->convertFallbackBlockNode($node),
        };
    }

    /**
     * @return list<string>
     */
    private function convertContainerNode(DOMNode $node): array
    {
        $blocks = [];
        $inlineBuffer = '';

        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement && $this->isBlockTag($childNode->tagName)) {
                $inlineText = $this->cleanInline($inlineBuffer);
                if ($inlineText !== '') {
                    $blocks[] = $inlineText;
                }

                $inlineBuffer = '';

                foreach ($this->convertBlockNode($childNode) as $childBlock) {
                    if (trim($childBlock) !== '') {
                        $blocks[] = $childBlock;
                    }
                }

                continue;
            }

            $inlineBuffer .= $this->convertInlineNode($childNode);
        }

        $inlineText = $this->cleanInline($inlineBuffer);
        if ($inlineText !== '') {
            $blocks[] = $inlineText;
        }

        return $blocks;
    }

    /**
     * @return list<string>
     */
    private function convertFallbackBlockNode(DOMElement $node): array
    {
        $nestedBlocks = $this->convertContainerNode($node);

        if ($nestedBlocks !== []) {
            return $nestedBlocks;
        }

        $inline = $this->cleanInline($this->convertInlineChildren($node));

        return $inline === '' ? [] : [$inline];
    }

    private function convertHeader(DOMElement $node, int $level): string
    {
        $content = $this->cleanInline($this->convertInlineChildren($node));

        if ($content === '') {
            return '';
        }

        return str_repeat('#', $level).' '.$content;
    }

    private function convertList(DOMElement $listNode, int $depth): string
    {
        $ordered = strtolower($listNode->tagName) === 'ol';
        $lines = [];
        $index = 1;

        foreach ($listNode->childNodes as $childNode) {
            if (! ($childNode instanceof DOMElement) || strtolower($childNode->tagName) !== 'li') {
                continue;
            }

            $itemText = '';
            $nestedLists = [];

            foreach ($childNode->childNodes as $itemNode) {
                if ($itemNode instanceof DOMElement && in_array(strtolower($itemNode->tagName), ['ul', 'ol'], true)) {
                    $nestedLists[] = $this->convertList($itemNode, $depth + 1);
                    continue;
                }

                $itemText .= $this->convertInlineNode($itemNode);
            }

            $marker = $ordered ? $index.'. ' : '- ';
            $prefix = str_repeat('    ', $depth);
            $itemContent = $this->cleanInline($itemText);
            $itemContent = preg_replace('/\s*\n\s*/u', ' ', $itemContent) ?? $itemContent;
            $lines[] = rtrim($prefix.$marker.$itemContent);

            foreach ($nestedLists as $nestedList) {
                $nestedLines = preg_split('/\r\n|\r|\n/', rtrim($nestedList, "\r\n")) ?: [];

                foreach ($nestedLines as $nestedLine) {
                    if ($nestedLine === '') {
                        continue;
                    }

                    $lines[] = $nestedLine;
                }
            }

            $index++;
        }

        return implode("\n", $lines);
    }

    private function convertInlineChildren(DOMNode $node): string
    {
        $result = '';

        foreach ($node->childNodes as $childNode) {
            $result .= $this->convertInlineNode($childNode);
        }

        return $result;
    }

    private function convertInlineNode(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return $this->normalizeText((string) $node->nodeValue);
        }

        if (! ($node instanceof DOMElement)) {
            return '';
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, ['strong', 'b'], true)) {
            $content = $this->cleanInline($this->convertInlineChildren($node));

            return $content === '' ? '' : '**'.$content.'**';
        }

        if (in_array($tag, ['em', 'i'], true)) {
            $content = $this->cleanInline($this->convertInlineChildren($node));

            return $content === '' ? '' : '*'.$content.'*';
        }

        if ($tag === 'br') {
            return "  \n";
        }

        if ($this->isBlockTag($tag)) {
            return $this->cleanInline($this->convertInlineChildren($node));
        }

        return $this->convertInlineChildren($node);
    }

    private function normalizeText(string $text): string
    {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = str_replace("\u{00A0}", ' ', $decoded);
        $decoded = preg_replace('/\s+/u', ' ', $decoded) ?? $decoded;

        return $decoded;
    }

    private function cleanInline(string $text): string
    {
        $normalized = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $normalized = preg_replace('/[ \t]*\n[ \t]*/u', "\n", $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function isBlockTag(string $tag): bool
    {
        return in_array(strtolower($tag), ['div', 'p', 'h1', 'h2', 'h3', 'ul', 'ol'], true);
    }

    private function shouldAccumulateAsInlineBlockNode(DOMNode $node): bool
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return true;
        }

        if (! ($node instanceof DOMElement)) {
            return false;
        }

        $tag = strtolower($node->tagName);

        if ($this->isBlockTag($tag)) {
            return false;
        }

        return ! in_array($tag, ['li'], true);
    }
}
