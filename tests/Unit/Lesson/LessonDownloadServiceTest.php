<?php

namespace Tests\Unit\Lesson;

use App\Services\Lesson\LessonDownloadService;
use App\Services\Lesson\YtDlpProgressParser;
use Tests\TestCase;
use YoutubeDl\Options;

class LessonDownloadServiceTest extends TestCase
{
    public function test_build_download_options_does_not_set_proxy_by_default(): void
    {
        config()->set('downloader.proxy', null);

        $service = $this->service();
        $options = $service->exposedBuildDownloadOptions('/tmp/downloads', 'https://youtube.com/watch?v=abc');

        $this->assertInstanceOf(Options::class, $options);
        $this->assertNull($options->toArray()['proxy']);
    }

    public function test_build_download_options_sets_proxy_when_configured(): void
    {
        config()->set('downloader.proxy', 'socks5://127.0.0.1:1080');

        $service = $this->service();
        $options = $service->exposedBuildDownloadOptions('/tmp/downloads', 'https://youtube.com/watch?v=abc');

        $this->assertSame('socks5://127.0.0.1:1080', $options->toArray()['proxy']);
    }

    private function service(): LessonDownloadService
    {
        return new class(new YtDlpProgressParser) extends LessonDownloadService
        {
            public function exposedBuildDownloadOptions(
                string $targetDirectory,
                string $url,
                ?string $referer = null,
            ): Options {
                return $this->buildDownloadOptions($targetDirectory, $url, $referer);
            }
        };
    }
}
