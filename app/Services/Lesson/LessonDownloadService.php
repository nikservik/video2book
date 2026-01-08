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
    /**
     * Скачивает аудио с YouTube и нормализует его в MP3 с нужными параметрами.
     *
     * @param  callable(float):void|null  $onProgress
     */
    public function downloadAndNormalize(Lesson $lesson, string $url, ?callable $onProgress = null): string
    {
        $disk = Storage::disk('local');
        $tempDirectory = 'downloader/'.$lesson->id.'/'.Str::uuid()->toString();
        $disk->makeDirectory($tempDirectory);
        $absoluteTempDirectory = $disk->path($tempDirectory);

        try {
            $downloadedFile = $this->downloadAudio($absoluteTempDirectory, $url, $onProgress);
            $relativeInput = $this->relativeLocalPath($downloadedFile);
            $outputPath = 'lessons/'.$lesson->id.'.mp3';

            $this->normalizeAudio($relativeInput, $outputPath, $lesson);
            $disk->delete($relativeInput);
            $onProgress?->__invoke(100.0);

            return $outputPath;
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Не удалось скачать или нормализовать аудио: '.$exception->getMessage(),
                0,
                $exception
            );
        } finally {
            $disk->deleteDirectory($tempDirectory);
        }
    }

    /**
     * @param  callable(float):void|null  $onProgress
     */
    private function downloadAudio(string $targetDirectory, string $url, ?callable $onProgress = null): string
    {
        $downloader = new YoutubeDl();
        $downloader->setBinPath(config('downloader.binary', 'yt-dlp'));

        if ($onProgress !== null) {
            $downloader->onProgress(static function (?string $progressTarget, string $percentage) use ($onProgress): void {
                $value = (float) str_replace('%', '', $percentage);
                $onProgress(max(0, min(100, $value)));
            });
        }

        $options = Options::create()
            ->downloadPath($targetDirectory)
            ->format('ba')
            ->noPlaylist(true)
            ->restrictFilenames(true)
            ->output('%(id)s.%(ext)s')
            ->url($url);

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

    private function normalizeAudio(string $inputPath, string $outputPath, Lesson $lesson): void
    {
        $settings = $this->resolveQualitySettings($lesson);

        $format = (new Mp3())
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

    private function relativeLocalPath(string $absolutePath): string
    {
        $root = rtrim(config('filesystems.disks.local.root', storage_path('app/private')), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with($absolutePath, $root)) {
            return $absolutePath;
        }

        return ltrim(substr($absolutePath, strlen($root)), DIRECTORY_SEPARATOR);
    }

    /**
     * @return array{sample_rate: int, bitrate: int}
     */
    private function resolveQualitySettings(Lesson $lesson): array
    {
        $quality = data_get($lesson->settings, 'quality', 'high');

        return match ($quality) {
            'low' => ['sample_rate' => 12000, 'bitrate' => 16],
            'medium' => ['sample_rate' => 16000, 'bitrate' => 32],
            default => ['sample_rate' => 22050, 'bitrate' => 64],
        };
    }
}
