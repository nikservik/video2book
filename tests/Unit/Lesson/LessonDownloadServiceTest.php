<?php

namespace Tests\Unit\Lesson;

use App\Models\Lesson;
use App\Models\Project;
use App\Models\ProjectTag;
use App\Services\Lesson\LessonDownloadService;
use App\Services\Lesson\YtDlpProgressParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use YoutubeDl\Options;

class LessonDownloadServiceTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_normalize_stored_audio_deletes_uploaded_temp_file_after_success(): void
    {
        Storage::fake('local');

        $lesson = $this->createLesson();
        $sourcePath = 'downloader/'.$lesson->id.'/upload-test/uploaded.wav';
        Storage::disk('local')->put($sourcePath, 'raw-audio');

        $service = $this->service(
            normalizeAudioBehavior: function (string $inputPath, string $outputPath): void {
                Storage::disk('local')->put($outputPath, 'normalized');
            }
        );

        $result = $service->normalizeStoredAudio($lesson, $sourcePath);

        $this->assertSame('lessons/'.$lesson->id.'.mp3', $result['path']);
        $this->assertNull($result['duration_seconds']);
        Storage::disk('local')->assertMissing($sourcePath);
    }

    public function test_normalize_stored_audio_deletes_uploaded_temp_file_after_failure(): void
    {
        Storage::fake('local');

        $lesson = $this->createLesson();
        $sourcePath = 'downloader/'.$lesson->id.'/upload-fail/uploaded.wav';
        Storage::disk('local')->put($sourcePath, 'raw-audio');

        $service = $this->service(
            normalizeAudioBehavior: function (): void {
                throw new RuntimeException('normalize failed');
            }
        );

        try {
            $service->normalizeStoredAudio($lesson, $sourcePath);
            $this->fail('Expected RuntimeException to be thrown.');
        } catch (RuntimeException) {
            // expected
        }

        Storage::disk('local')->assertMissing($sourcePath);
    }

    public function test_download_and_normalize_deletes_youtube_temp_file_after_success(): void
    {
        Storage::fake('local');

        $lesson = $this->createLesson();

        $service = $this->service(
            downloadAudioBehavior: function (string $targetDirectory): string {
                $absolutePath = rtrim($targetDirectory, '/').'/downloaded.wav';
                file_put_contents($absolutePath, 'downloaded-audio');

                return $absolutePath;
            },
            normalizeAudioBehavior: function (string $inputPath, string $outputPath): void {
                Storage::disk('local')->put($outputPath, 'normalized');
            }
        );

        $result = $service->downloadAndNormalize($lesson, 'https://youtube.com/watch?v=abc');

        $this->assertSame('lessons/'.$lesson->id.'.mp3', $result['path']);
        $this->assertNull($result['duration_seconds']);
        $this->assertSame([], Storage::disk('local')->allFiles('downloader/'.$lesson->id));
    }

    public function test_download_and_normalize_deletes_youtube_temp_file_after_failure(): void
    {
        Storage::fake('local');

        $lesson = $this->createLesson();

        $service = $this->service(
            downloadAudioBehavior: function (string $targetDirectory): string {
                $absolutePath = rtrim($targetDirectory, '/').'/downloaded.wav';
                file_put_contents($absolutePath, 'downloaded-audio');

                return $absolutePath;
            },
            normalizeAudioBehavior: function (): void {
                throw new RuntimeException('normalize failed');
            }
        );

        try {
            $service->downloadAndNormalize($lesson, 'https://youtube.com/watch?v=abc');
            $this->fail('Expected RuntimeException to be thrown.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame([], Storage::disk('local')->allFiles('downloader/'.$lesson->id));
    }

    private function service(
        ?\Closure $downloadAudioBehavior = null,
        ?\Closure $normalizeAudioBehavior = null,
    ): LessonDownloadService {
        return new class(new YtDlpProgressParser, $downloadAudioBehavior, $normalizeAudioBehavior) extends LessonDownloadService
        {
            public function __construct(
                YtDlpProgressParser $progressParser,
                private readonly ?\Closure $downloadAudioBehavior = null,
                private readonly ?\Closure $normalizeAudioBehavior = null,
            ) {
                parent::__construct($progressParser);
            }

            public function exposedBuildDownloadOptions(
                string $targetDirectory,
                string $url,
                ?string $referer = null,
            ): Options {
                return $this->buildDownloadOptions($targetDirectory, $url, $referer);
            }

            protected function downloadAudio(
                string $targetDirectory,
                string $url,
                ?callable $onProgress = null,
                ?string $referer = null,
            ): string {
                if ($this->downloadAudioBehavior !== null) {
                    return ($this->downloadAudioBehavior)($targetDirectory, $url, $onProgress, $referer);
                }

                return parent::downloadAudio($targetDirectory, $url, $onProgress, $referer);
            }

            protected function normalizeAudio(string $inputPath, string $outputPath, Lesson $lesson): void
            {
                if ($this->normalizeAudioBehavior !== null) {
                    ($this->normalizeAudioBehavior)($inputPath, $outputPath, $lesson);

                    return;
                }

                parent::normalizeAudio($inputPath, $outputPath, $lesson);
            }
        };
    }

    private function createLesson(): Lesson
    {
        ProjectTag::query()->create([
            'slug' => 'default',
            'description' => null,
        ]);

        $project = Project::query()->create([
            'name' => 'Test Project',
            'tags' => null,
        ]);

        return Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Test Lesson',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => ['quality' => 'low'],
        ]);
    }
}
