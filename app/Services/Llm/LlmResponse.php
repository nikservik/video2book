<?php

namespace App\Services\Llm;

use Closure;
use Generator;
use RuntimeException;

final class LlmResponse
{
    private ?LlmUsage $usage;

    private bool $consumed = false;

    /**
     * @param  Closure():iterable<LlmResponseChunk>  $producer
     */
    private function __construct(
        private readonly bool $streaming,
        private readonly Closure $producer,
        ?LlmUsage $usage = null,
    ) {
        $this->usage = $usage;
    }

    /**
     * @param  Closure():Generator<LlmResponseChunk>  $producer
     */
    public static function streaming(Closure $producer): self
    {
        return new self(true, $producer);
    }

    public static function fromText(string $content, ?LlmUsage $usage = null, array $meta = []): self
    {
        return new self(false, static function () use ($content, $usage, $meta): Generator {
            yield LlmResponseChunk::final($content, $usage, $meta);
        }, $usage);
    }

    public function isStreaming(): bool
    {
        return $this->streaming;
    }

    public function usage(): ?LlmUsage
    {
        return $this->usage;
    }

    /**
     * @param  callable(LlmResponseChunk):void|null  $listener
     */
    public function stream(?callable $listener = null): string
    {
        if ($this->consumed) {
            throw new RuntimeException('LLM response was already consumed. Create a new response instance to stream again.');
        }

        $this->consumed = true;
        $buffer = '';

        foreach (($this->producer)() as $chunk) {
            $buffer .= $chunk->content;

            if ($chunk->usage !== null) {
                $this->usage = $chunk->usage;
            }

            if ($listener !== null) {
                $listener($chunk);
            }
        }

        return $buffer;
    }

    public function collect(): string
    {
        return $this->stream();
    }
}
