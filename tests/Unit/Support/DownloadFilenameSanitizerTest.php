<?php

namespace Tests\Unit\Support;

use App\Support\DownloadFilenameSanitizer;
use Tests\TestCase;

class DownloadFilenameSanitizerTest extends TestCase
{
    public function test_it_preserves_utf_and_removes_invalid_filename_characters(): void
    {
        $sanitizer = app(DownloadFilenameSanitizer::class);

        $result = $sanitizer->sanitize('Проект: "Большой" / Архив?*');

        $this->assertSame('Проект Большой Архив', $result);
    }

    public function test_it_can_join_sanitized_parts_with_separator(): void
    {
        $sanitizer = app(DownloadFilenameSanitizer::class);

        $result = $sanitizer->join([
            'Урок: "Laravel"',
            'Шаг / Livewire*',
        ], ' - ');

        $this->assertSame('Урок Laravel - Шаг Livewire', $result);
    }

    public function test_it_returns_fallback_when_value_becomes_empty_after_sanitization(): void
    {
        $sanitizer = app(DownloadFilenameSanitizer::class);

        $result = $sanitizer->sanitize(' /:*?"<>| ', 'project-export');

        $this->assertSame('project-export', $result);
    }
}
