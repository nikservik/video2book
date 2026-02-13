<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\LlmResponse;
use App\Services\Llm\LlmResponseChunk;
use App\Services\Llm\LlmUsage;
use PHPUnit\Framework\TestCase;

class LlmResponseTest extends TestCase
{
    public function test_static_response_collects_text_and_usage(): void
    {
        $usage = new LlmUsage(inputTokens: 10, outputTokens: 5);
        $response = LlmResponse::fromText('Hello world', $usage);

        $this->assertSame('Hello world', $response->collect());
        $this->assertSame($usage, $response->usage());
    }

    public function test_streaming_response_invokes_listener_and_collects_usage(): void
    {
        $response = LlmResponse::streaming(function () {
            yield LlmResponseChunk::partial('Hello ');
            yield LlmResponseChunk::final('world', new LlmUsage(inputTokens: 4, outputTokens: 7));
        });

        $chunks = [];
        $text = $response->stream(function (LlmResponseChunk $chunk) use (&$chunks): void {
            $chunks[] = $chunk->content;
        });

        $this->assertSame(['Hello ', 'world'], $chunks);
        $this->assertSame('Hello world', $text);
        $this->assertSame(7, $response->usage()?->outputTokens);
    }

    public function test_stream_cannot_be_consumed_twice(): void
    {
        $response = LlmResponse::streaming(function () {
            yield LlmResponseChunk::final('done', new LlmUsage);
        });

        $response->collect();

        $this->expectExceptionMessage('already consumed');
        $response->collect();
    }
}
