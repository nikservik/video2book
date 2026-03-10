<?php

namespace App\Mcp\Support;

class McpUrlTokenRedactor
{
    public function sanitize(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return (string) preg_replace(
            '#/mcp/video2book/[^/?]+#',
            '/mcp/video2book/[redacted]',
            $value,
        );
    }

    public function currentRequestUrl(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        if (! $request->is('mcp/video2book/*')) {
            return null;
        }

        return $this->sanitize($request->fullUrl());
    }
}
