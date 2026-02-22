<?php

namespace App\Services\Lesson;

use App\Models\Lesson;
use FFMpeg\Format\Audio\Mp3;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use RuntimeException;
use Throwable;
use YoutubeDl\Options;
use YoutubeDl\YoutubeDl;

class LessonDownloadService
{
    public function __construct(private readonly YtDlpProgressParser $progressParser) {}

    /**
     * Скачивает аудио с YouTube и нормализует его в MP3 с нужными параметрами.
     *
     * @param  callable(float):void|null  $onProgress
     * @return array{path: string, duration_seconds: int|null}
     */
    public function downloadAndNormalize(
        Lesson $lesson,
        string $url,
        ?callable $onProgress = null,
        ?string $referer = null,
    ): array {
        $disk = Storage::disk('local');
        $tempDirectory = 'downloader/'.$lesson->id.'/'.Str::uuid()->toString();
        $disk->makeDirectory($tempDirectory);
        $absoluteTempDirectory = $disk->path($tempDirectory);
        $diskRoot = rtrim($disk->path(''), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $relativeInput = null;

        try {
            $downloadedFile = $this->downloadAudio(
                $absoluteTempDirectory,
                $url,
                $onProgress,
                $referer,
            );
            $relativeInput = $this->relativeLocalPath($downloadedFile, $diskRoot);
            $outputPath = 'lessons/'.$lesson->id.'.mp3';

            $this->normalizeAudio($relativeInput, $outputPath, $lesson);
            $durationSeconds = $this->resolveAudioDurationInSeconds($outputPath);
            $onProgress?->__invoke(100.0);

            return [
                'path' => $outputPath,
                'duration_seconds' => $durationSeconds,
            ];
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Не удалось скачать или нормализовать аудио: '.$exception->getMessage(),
                0,
                $exception
            );
        } finally {
            if (is_string($relativeInput) && $relativeInput !== '') {
                $disk->delete($relativeInput);
            }
        }
    }

    /**
     * @return array{path: string, duration_seconds: int|null}
     */
    public function normalizeStoredAudio(Lesson $lesson, string $sourcePath): array
    {
        $disk = Storage::disk('local');

        try {
            if (! $disk->exists($sourcePath)) {
                throw new RuntimeException('Загруженный аудиофайл не найден во временном хранилище.');
            }

            $outputPath = 'lessons/'.$lesson->id.'.mp3';

            $this->normalizeAudio($sourcePath, $outputPath, $lesson);
            $durationSeconds = $this->resolveAudioDurationInSeconds($outputPath);

            return [
                'path' => $outputPath,
                'duration_seconds' => $durationSeconds,
            ];
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Не удалось нормализовать загруженное аудио: '.$exception->getMessage(),
                0,
                $exception
            );
        } finally {
            $disk->delete($sourcePath);
        }
    }

    /**
     * @param  callable(float):void|null  $onProgress
     */
    protected function downloadAudio(
        string $targetDirectory,
        string $url,
        ?callable $onProgress = null,
        ?string $referer = null,
    ): string {
        $downloader = new YoutubeDl;
        $downloader->setBinPath(config('downloader.binary', 'yt-dlp'));

        if ($onProgress !== null) {
            $lastReportedProgress = null;

            $emitProgress = static function (float $value) use ($onProgress, &$lastReportedProgress): void {
                $progress = max(0, min(100, $value));

                if ($lastReportedProgress !== null && abs($lastReportedProgress - $progress) < 0.1) {
                    return;
                }

                $lastReportedProgress = $progress;
                $onProgress($progress);
            };

            $downloader->onProgress(static function (?string $progressTarget, string $percentage) use ($emitProgress): void {
                $value = (float) str_replace('%', '', $percentage);
                $emitProgress($value);
            });

            $downloader->debug(function (string $type, string $buffer) use ($emitProgress): void {
                foreach ($this->progressParser->extractPercentages($buffer) as $percentage) {
                    $emitProgress($percentage);
                }
            });
        }

        $options = $this->buildDownloadOptions(
            targetDirectory: $targetDirectory,
            url: $url,
            referer: $referer,
        );

        $collection = $downloader->download($options);

        foreach ($collection->getVideos() as $video) {
            if ($video->getError() !== null) {
                throw new RuntimeException($video->getError());
            }

            $file = $video->getFile();
            if ($file !== null && $file->isFile()) {
                return $file->getPathname();
            }
        }

        throw new RuntimeException('Youtube-dl не вернул файл для скачанного видео.');
    }

    protected function buildDownloadOptions(
        string $targetDirectory,
        string $url,
        ?string $referer = null,
    ): Options {
        $options = Options::create()
            ->downloadPath($targetDirectory)
            ->format('ba')
            ->noPlaylist(true)
            ->restrictFilenames(true)
            ->output('%(id)s.%(ext)s')
            ->url($url);

        if ($referer !== null) {
            $options = $options->referer($referer);
        }

        $proxy = $this->configuredProxy();

        if ($proxy !== null) {
            $options = $options->proxy($proxy);
        }

        return $options;
    }

    protected function normalizeAudio(string $inputPath, string $outputPath, Lesson $lesson): void
    {
        $settings = $this->resolveQualitySettings($lesson);

        $format = (new Mp3)
            ->setAudioKiloBitrate($settings['bitrate'])
            ->setAudioChannels(1);

        $exporter = FFMpeg::fromDisk('local')
            ->open($inputPath)
            ->export()
            ->toDisk('local')
            ->inFormat($format);

        $exporter->addFilter(['-ar', (string) $settings['sample_rate']]);
        $exporter->save($outputPath);
    }

    private function relativeLocalPath(string $absolutePath, string $diskRoot): string
    {
        if (! str_starts_with($absolutePath, $diskRoot)) {
            return $absolutePath;
        }

        return ltrim(substr($absolutePath, strlen($diskRoot)), DIRECTORY_SEPARATOR);
    }

    /**
     * @return array{sample_rate: int, bitrate: int}
     */
    private function resolveQualitySettings(Lesson $lesson): array
    {
        $quality = data_get($lesson->settings, 'quality', 'low');

        return match ($quality) {
            'low' => ['sample_rate' => 12000, 'bitrate' => 16],
            'medium' => ['sample_rate' => 16000, 'bitrate' => 32],
            default => ['sample_rate' => 22050, 'bitrate' => 64],
        };
    }

    private function configuredProxy(): ?string
    {
        $proxy = trim((string) config('downloader.proxy', ''));

        return $proxy !== '' ? $proxy : null;
    }

    private function resolveAudioDurationInSeconds(string $audioPath): ?int
    {
        try {
            $duration = FFMpeg::fromDisk('local')
                ->open($audioPath)
                ->getDurationInSeconds();
        } catch (Throwable) {
            return null;
        }

        return $duration > 0 ? (int) $duration : null;
    }
}
