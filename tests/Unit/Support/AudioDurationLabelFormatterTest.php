<?php

namespace Tests\Unit\Support;

use App\Support\AudioDurationLabelFormatter;
use Tests\TestCase;

class AudioDurationLabelFormatterTest extends TestCase
{
    public function test_it_formats_duration_with_hours_and_minutes(): void
    {
        $label = app(AudioDurationLabelFormatter::class)->format(5415);

        $this->assertSame('1ч 30м', $label);
    }

    public function test_it_formats_duration_without_hours_when_less_than_one_hour(): void
    {
        $label = app(AudioDurationLabelFormatter::class)->format(1830);

        $this->assertSame('31м', $label);
    }

    public function test_it_rounds_minutes_from_seconds(): void
    {
        $formatter = app(AudioDurationLabelFormatter::class);

        $this->assertSame('1м', $formatter->format(30));
        $this->assertSame('0м', $formatter->format(29));
    }

    public function test_it_returns_null_for_invalid_or_non_positive_duration(): void
    {
        $formatter = app(AudioDurationLabelFormatter::class);

        $this->assertNull($formatter->format(null));
        $this->assertNull($formatter->format(0));
        $this->assertNull($formatter->format(-120));
        $this->assertNull($formatter->format('abc'));
    }
}
