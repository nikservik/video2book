<?php

namespace Tests\Unit\Lesson;

use App\Services\Lesson\YtDlpProgressParser;
use Tests\TestCase;

class YtDlpProgressParserTest extends TestCase
{
    public function test_it_extracts_progress_from_standard_download_line(): void
    {
        $parser = new YtDlpProgressParser;

        $buffer = '[download]  37.3% of 10.52MiB at 1.10MiB/s ETA 00:06';

        $this->assertSame([37.3], $parser->extractPercentages($buffer));
    }

    public function test_it_extracts_progress_from_fragmented_hls_line(): void
    {
        $parser = new YtDlpProgressParser;

        $buffer = '[download]  26.1% of ~ 47.77MiB at 1.24MiB/s ETA 00:37 (frag 31/117)';

        $this->assertSame([26.1], $parser->extractPercentages($buffer));
    }

    public function test_it_extracts_all_progress_values_from_combined_buffer(): void
    {
        $parser = new YtDlpProgressParser;

        $buffer = '[download]  0.0% of ~ 47.77MiB at 512.00KiB/s ETA 01:20'."\n"
            .'[download]  3.5% of ~ 47.77MiB at 1.01MiB/s ETA 00:55'."\n"
            .'[info] Some other line'."\n"
            .'[download]  100% of 47.77MiB in 00:42';

        $this->assertSame([0.0, 3.5, 100.0], $parser->extractPercentages($buffer));
    }
}
