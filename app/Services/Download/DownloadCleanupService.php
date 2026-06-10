<?php

declare(strict_types=1);

namespace App\Services\Download;

use App\Commands\DownloadMediaCommand;
use App\Enums\MediaItemStatus;
use App\Models\MediaItem;
use App\Config\TubecastConfig;

use function Tempest\Support\Filesystem\create_directory;
use function Tempest\Support\Filesystem\delete_directory;

final class DownloadCleanupService
{
    public function __construct(
        private TubecastConfig $config,
    ) {
    }

    /** @return array{deletedFiles: int, resetItems: int, clearedCommands: int} */
    public function nukeAll(): array
    {
        $deletedFiles = 0;

        foreach ([$this->config->downloadsPath, $this->config->podcastPath] as $directory) {
            $deletedFiles += $this->wipeDirectory($directory);
        }

        $resetItems = $this->resetMediaItems();
        $clearedCommands = $this->clearPendingDownloadCommands();

        return [
            'deletedFiles' => $deletedFiles,
            'resetItems' => $resetItems,
            'clearedCommands' => $clearedCommands,
        ];
    }

    private function wipeDirectory(string $directory): int
    {
        if (! is_dir($directory)) {
            create_directory($directory);

            return 0;
        }

        $count = $this->countFiles($directory);
        delete_directory($directory, recursive: true);
        create_directory($directory);

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

    private function resetMediaItems(): int
    {
        $reset = 0;

        foreach (MediaItem::select()->all() as $item) {
            $hadMedia = $item->filePath !== null
                || $item->podcastFilePath !== null
                || $item->status === MediaItemStatus::Completed
                || $item->status === MediaItemStatus::Downloading
                || $item->status === MediaItemStatus::Failed
                || $item->status === MediaItemStatus::Throttled;

            if (! $hadMedia) {
                continue;
            }

            $item->filePath = null;
            $item->podcastFilePath = null;
            $item->podcastFileSize = null;
            $item->podcastMime = null;
            $item->status = MediaItemStatus::Discovered;
            $item->save();
            $reset++;
        }

        return $reset;
    }

    private function clearPendingDownloadCommands(): int
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

            if ($command instanceof DownloadMediaCommand) {
                unlink($path);
                $cleared++;
            }
        }

        return $cleared;
    }
}
