<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\DownloadMediaCommand;
use App\Commands\FastIndexSourceCommand;
use App\Commands\FullIndexSourceCommand;
use App\Models\Feed;
use App\Models\MediaItem;
use App\Models\Source;
use App\Support\ModelId;
use App\TubecastConfig;

use function Tempest\Support\Filesystem\delete_directory;

final class SourceDeletionService
{
    public function __construct(
        private OutputPathBuilder $paths,
        private TubecastConfig $config,
    ) {
    }

    /** @return array{deletedFiles: int, deletedEpisodes: int, clearedCommands: int} */
    public function delete(Source $source): array
    {
        $sourceId = ModelId::int($source->id);
        $items = MediaItem::select()
            ->where('sourceId = ?', $sourceId)
            ->all();

        $itemIds = [];
        $deletedFiles = 0;

        foreach ($items as $item) {
            $itemIds[] = ModelId::int($item->id);
            $deletedFiles += $this->deleteItemFiles($sourceId, $item);
            $item->delete();
        }

        foreach (Feed::select()->where('sourceId = ?', $sourceId)->all() as $feed) {
            $feed->delete();
        }

        $deletedFiles += $this->deleteSourcePodcastDirectory($sourceId);

        $clearedCommands = $this->clearPendingDownloadCommands($itemIds);
        $clearedCommands += $this->clearPendingIndexCommands($sourceId);

        $deletedEpisodes = count($items);
        $source->delete();

        return [
            'deletedFiles' => $deletedFiles,
            'deletedEpisodes' => $deletedEpisodes,
            'clearedCommands' => $clearedCommands,
        ];
    }

    private function deleteItemFiles(int $sourceId, MediaItem $item): int
    {
        $deleted = 0;

        foreach ([$item->filePath, $item->podcastFilePath] as $path) {
            if ($path !== null && is_file($path)) {
                unlink($path);
                $deleted++;
            }
        }

        foreach (
            [
                $this->paths->findDownloadedFile($item->ytId),
                $this->paths->findPartialFileForVideo($item->ytId),
                $this->paths->findInfoJsonForEpisode($sourceId, $item->ytId),
            ] as $path
        ) {
            if ($path !== null && is_file($path)) {
                unlink($path);
                $deleted++;
            }
        }

        return $deleted;
    }

    private function deleteSourcePodcastDirectory(int $sourceId): int
    {
        $directory = rtrim($this->config->podcastPath, '/') . '/' . $sourceId;

        if (! is_dir($directory)) {
            return 0;
        }

        $count = $this->countFiles($directory);
        delete_directory($directory, recursive: true);

        return $count;
    }

    private function countFiles(string $directory): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }

        return $count;
    }

    /** @param list<int> $mediaItemIds */
    private function clearPendingDownloadCommands(array $mediaItemIds): int
    {
        if ($mediaItemIds === []) {
            return 0;
        }

        return $this->clearPendingCommands(static function (object $command) use ($mediaItemIds): bool {
            return $command instanceof DownloadMediaCommand
                && in_array($command->mediaItemId, $mediaItemIds, true);
        });
    }

    private function clearPendingIndexCommands(int $sourceId): int
    {
        return $this->clearPendingCommands(static function (object $command) use ($sourceId): bool {
            return ($command instanceof FullIndexSourceCommand && $command->sourceId === $sourceId)
                || ($command instanceof FastIndexSourceCommand && $command->sourceId === $sourceId);
        });
    }

    /** @param callable(object): bool $matches */
    private function clearPendingCommands(callable $matches): int
    {
        $directory = $this->config->storedCommandsPath();

        if (! is_dir($directory)) {
            return 0;
        }

        $cleared = 0;

        foreach (glob($directory . '/*.pending.txt') ?: [] as $path) {
            $payload = @file_get_contents($path);

            if ($payload === false) {
                continue;
            }

            try {
                $command = unserialize($payload, ['allowed_classes' => true]);
            } catch (\Throwable) {
                continue;
            }

            if (! is_object($command) || ! $matches($command)) {
                continue;
            }

            unlink($path);
            $cleared++;
        }

        return $cleared;
    }
}
