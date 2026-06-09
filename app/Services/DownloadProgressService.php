<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MediaItemStatus;
use App\Models\MediaItem;

final class DownloadProgressService
{
    public function __construct(
        private OutputPathBuilder $paths,
    ) {
    }

    public function forItem(MediaItem $item): DownloadProgress
    {
        if ($item->status !== MediaItemStatus::Downloading) {
            return new DownloadProgress(
                active: false,
                percent: $item->status === MediaItemStatus::Completed ? 100 : null,
                bytesReceived: null,
                bytesTotal: null,
                indeterminate: false,
            );
        }

        $bytesReceived = $this->paths->receivedBytesForEpisode($item->sourceId, $item->ytId);
        $bytesTotal = $this->paths->expectedBytesForEpisode($item->sourceId, $item->ytId, $item->metadataJson);

        if ($bytesReceived !== null && $bytesTotal !== null && $bytesTotal > 0) {
            return new DownloadProgress(
                active: true,
                percent: (int) min(99, round($bytesReceived / $bytesTotal * 100)),
                bytesReceived: $bytesReceived,
                bytesTotal: $bytesTotal,
                indeterminate: false,
            );
        }

        if ($bytesReceived !== null && $bytesReceived > 0) {
            return new DownloadProgress(
                active: true,
                percent: null,
                bytesReceived: $bytesReceived,
                bytesTotal: null,
                indeterminate: true,
            );
        }

        return new DownloadProgress(
            active: true,
            percent: null,
            bytesReceived: null,
            bytesTotal: null,
            indeterminate: true,
        );
    }

    /** @param array{total: int, completed: int, downloading: int, failed: int, pending: int} $stats */
    public function forSource(int $sourceId, array $stats): DownloadProgress
    {
        if ($stats['total'] === 0) {
            return new DownloadProgress(
                active: false,
                percent: 0,
                bytesReceived: null,
                bytesTotal: null,
                indeterminate: false,
            );
        }

        $weight = (float) $stats['completed'];
        $sawActivity = false;

        if ($stats['downloading'] > 0) {
            $downloading = MediaItem::select()
                ->where('sourceId = ? AND status = ?', $sourceId, MediaItemStatus::Downloading->value)
                ->all();

            foreach ($downloading as $item) {
                $itemProgress = $this->forItem($item);

                if ($itemProgress->percent !== null) {
                    $weight += $itemProgress->percent / 100;
                    $sawActivity = true;
                } elseif ($itemProgress->bytesReceived !== null) {
                    $weight += 0.05;
                    $sawActivity = true;
                }
            }
        }

        $percent = (int) min(100, round($weight / $stats['total'] * 100));
        $active = $stats['downloading'] > 0 || $stats['pending'] > 0;

        return new DownloadProgress(
            active: $active,
            percent: $percent,
            bytesReceived: $stats['completed'],
            bytesTotal: $stats['total'],
            indeterminate: $active && $stats['downloading'] > 0 && ! $sawActivity,
        );
    }
}
