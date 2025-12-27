<?php

namespace App\Services\Llm;

final class LlmResponseChunk
{
    public function __construct(
        public readonly string $content,
        public readonly bool $isFinal = false,
        public readonly ?LlmUsage $usage = null,
        public readonly array $meta = [],
    ) {
    }

    public static function partial(string $content, array $meta = []): self
    {
        return new self($content, false, null, $meta);
    }

    public static function final(string $content = '', ?LlmUsage $usage = null, array $meta = []): self
    {
        return new self($content, true, $usage, $meta);
    }
}
