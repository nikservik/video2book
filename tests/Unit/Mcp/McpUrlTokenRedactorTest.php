<?php

namespace Tests\Unit\Mcp;

use App\Mcp\Support\McpUrlTokenRedactor;
use Tests\TestCase;

class McpUrlTokenRedactorTest extends TestCase
{
    public function test_it_redacts_token_inside_mcp_url_string(): void
    {
        $redactor = app(McpUrlTokenRedactor::class);

        $this->assertSame(
            'https://example.com/mcp/video2book/[redacted]?foo=bar',
            $redactor->sanitize('https://example.com/mcp/video2book/secret-token?foo=bar')
        );
    }

    public function test_it_leaves_non_mcp_url_untouched(): void
    {
        $redactor = app(McpUrlTokenRedactor::class);

        $this->assertSame(
            'https://example.com/projects/1',
            $redactor->sanitize('https://example.com/projects/1')
        );
    }
}
