<?php

declare(strict_types=1);

namespace App\Services\Download;

use App\Config\TubecastConfig;

final class OutputPathBuilder
{
    public function __construct(
        private TubecastConfig $config,
    ) {
    }

    public function defaultTemplate(): string
    {
        return '%(uploader)s/%(title)s [%(id)s].%(ext)s';
    }

    public function podcastOutputTemplate(int $sourceId): string
    {
        return rtrim($this->config->podcastPath, '/') . '/' . $sourceId . '/%(id)s.%(ext)s';
    }

    public function findPodcastFile(int $sourceId, string $videoId): ?string
    {
        $directory = rtrim($this->config->podcastPath, '/') . '/' . $sourceId;

        if (! is_dir($directory)) {
            return null;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        $newest = null;
        $newestTime = 0;

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $name = $file->getFilename();

            if (! str_contains($name, $videoId) || str_ends_with($name, '.info.json')) {
                continue;
            }

            $mtime = $file->getMTime();

            if ($mtime >= $newestTime) {
                $newestTime = $mtime;
                $newest = $file->getPathname();
            }
        }

        return $newest;
    }

    public function receivedBytesForEpisode(int $sourceId, string $videoId): ?int
    {
        $partial = $this->findPartialFileForEpisode($sourceId, $videoId);

        if ($partial !== null) {
            $bytes = filesize($partial);

            return is_int($bytes) && $bytes > 0 ? $bytes : null;
        }

        $largest = 0;

        foreach ($this->episodeStorageDirectories($sourceId) as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $name = $file->getFilename();

                if (! str_contains($name, $videoId) || str_ends_with($name, '.info.json')) {
                    continue;
                }

                $bytes = $file->getSize();

                if ($bytes > $largest) {
                    $largest = $bytes;
                }
            }
        }

        return $largest > 0 ? $largest : null;
    }

    public function expectedBytesForEpisode(int $sourceId, string $videoId, ?string $metadataJson = null): ?int
    {
        $fromMetadata = $this->expectedBytesFromMetadata($metadataJson);

        if ($fromMetadata !== null) {
            return $fromMetadata;
        }

        $infoFile = $this->findInfoJsonForEpisode($sourceId, $videoId);

        if ($infoFile === null) {
            return null;
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode((string) file_get_contents($infoFile), true, 512, JSON_THROW_ON_ERROR);

            return $this->expectedBytesFromMetadata(json_encode($data, JSON_THROW_ON_ERROR));
        } catch (\JsonException) {
            return null;
        }
    }

    public function findDownloadedFile(string $videoId): ?string
    {
        $pattern = $this->config->downloadsPath . '/**/*' . $videoId . '*';

        $matches = glob($pattern, GLOB_BRACE) ?: [];

        if ($matches === []) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $this->config->downloadsPath,
                    \FilesystemIterator::SKIP_DOTS,
                ),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                if (str_contains($file->getFilename(), $videoId)) {
                    return $file->getPathname();
                }
            }

            return null;
        }

        usort($matches, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $matches[0];
    }

    public function partialBytesForVideo(string $videoId): ?int
    {
        return $this->receivedBytesForEpisode(0, $videoId);
    }

    /** @deprecated Use expectedBytesForEpisode() */
    public function expectedBytesForVideo(string $videoId, ?string $metadataJson = null): ?int
    {
        return $this->expectedBytesForEpisode(0, $videoId, $metadataJson);
    }

    public function findPartialFileForEpisode(int $sourceId, string $videoId): ?string
    {
        $newest = null;
        $newestTime = 0;

        foreach ($this->episodeStorageDirectories($sourceId) as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $name = $file->getFilename();

                if (! str_contains($name, $videoId)) {
                    continue;
                }

                if (! str_ends_with($name, '.part') && ! str_contains($name, '.part-')) {
                    continue;
                }

                $mtime = $file->getMTime();

                if ($mtime >= $newestTime) {
                    $newestTime = $mtime;
                    $newest = $file->getPathname();
                }
            }
        }

        return $newest;
    }

    /** @deprecated Use findPartialFileForEpisode() */
    public function findPartialFileForVideo(string $videoId): ?string
    {
        return $this->findPartialFileForEpisode(0, $videoId);
    }

    public function findInfoJsonForEpisode(int $sourceId, string $videoId): ?string
    {
        foreach ($this->episodeStorageDirectories($sourceId) as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $name = $file->getFilename();

                if (str_contains($name, $videoId) && str_ends_with($name, '.info.json')) {
                    return $file->getPathname();
                }
            }
        }

        return null;
    }

    /** @return list<string> */
    private function episodeStorageDirectories(int $sourceId): array
    {
        $directories = [rtrim($this->config->downloadsPath, '/')];

        if ($sourceId > 0) {
            $directories[] = rtrim($this->config->podcastPath, '/') . '/' . $sourceId;
        }

        return $directories;
    }

    private function expectedBytesFromMetadata(?string $metadataJson): ?int
    {
        if ($metadataJson === null || $metadataJson === '') {
            return null;
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($metadataJson, true, 512, JSON_THROW_ON_ERROR);

            foreach (['filesize', 'filesize_approx'] as $key) {
                if (isset($data[$key]) && is_numeric($data[$key])) {
                    return (int) $data[$key];
                }
            }
        } catch (\JsonException) {
        }

        return null;
    }
}
