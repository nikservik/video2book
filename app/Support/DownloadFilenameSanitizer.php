<?php

namespace App\Support;

class DownloadFilenameSanitizer
{
    /**
     * @param  array<int, string|null>  $parts
     */
    public function join(array $parts, string $separator = '-', string $fallback = 'export'): string
    {
        $sanitizedParts = array_values(array_filter(
            array_map(
                fn (?string $part): string => $this->normalize((string) $part),
                $parts
            ),
            fn (string $part): bool => $part !== ''
        ));

        if ($sanitizedParts === []) {
            return $fallback;
        }

        return $this->sanitize(implode($separator, $sanitizedParts), $fallback);
    }

    public function sanitize(string $value, string $fallback = 'export'): string
    {
        $sanitized = $this->normalize($value);

        return $sanitized !== '' ? $sanitized : $fallback;
    }

    private function normalize(string $value): string
    {
        $sanitized = preg_replace('/[\p{C}<>:"\/\\\\|?*]+/u', ' ', $value) ?? '';
        $sanitized = preg_replace('/\s+/u', ' ', $sanitized) ?? '';

        return trim($sanitized, ". \t\n\r\0\x0B");
    }
}
