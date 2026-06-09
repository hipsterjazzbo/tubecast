<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\MediaItemStatus;
use App\Models\MediaItem;
use App\Services\DownloadProgress;
use Tempest\DateTime\FormatPattern;

final class MediaItemPresenter
{
    public static function for(MediaItem $item, ?DownloadProgress $progress = null): EpisodePresentation
    {
        $bytes = $item->podcastFileSize
            ?? ($item->filePath !== null && is_file($item->filePath) ? filesize($item->filePath) : null);
        $progress ??= new DownloadProgress(
            active: $item->status === MediaItemStatus::Downloading,
            percent: $item->status === MediaItemStatus::Completed ? 100 : null,
            bytesReceived: null,
            bytesTotal: null,
            indeterminate: false,
        );

        return new EpisodePresentation(
            thumbnailUrl: self::thumbnail($item->metadataJson, $item->ytId),
            statusLabel: ucfirst(str_replace('_', ' ', $item->status->value)),
            statusColorClass: self::statusColor($item->status->value) . ' ring-1 ring-inset',
            publishedLabel: $item->publishedAt?->format(FormatPattern::SIMPLE_DATE),
            durationLabel: self::formatDuration($item->durationSeconds) ?? '—',
            fileSizeLabel: self::formatFileSize(is_int($bytes) ? $bytes : null),
            showProgressBar: $progress->active,
            progressWidthStyle: $progress->widthStyle(),
            progressIndeterminate: $progress->indeterminate,
            progressLabel: $progress->label(),
            progressBarClass: $progress->barClass,
        );
    }

    public static function thumbnail(?string $metadataJson, string $ytId): string
    {
        if ($metadataJson !== null && $metadataJson !== '') {
            try {
                /** @var array<string, mixed> $data */
                $data = json_decode($metadataJson, true, 512, JSON_THROW_ON_ERROR);

                if (isset($data['thumbnail']) && is_string($data['thumbnail'])) {
                    return $data['thumbnail'];
                }

                if (isset($data['thumbnails']) && is_array($data['thumbnails'])) {
                    $last = end($data['thumbnails']);

                    if (is_array($last) && isset($last['url']) && is_string($last['url'])) {
                        return $last['url'];
                    }
                }
            } catch (\JsonException) {
            }
        }

        return 'https://i.ytimg.com/vi/' . rawurlencode($ytId) . '/mqdefault.jpg';
    }

    public static function formatDuration(?int $seconds): ?string
    {
        if ($seconds === null || $seconds <= 0) {
            return null;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    }

    public static function formatFileSize(?int $bytes): ?string
    {
        if ($bytes === null || $bytes <= 0) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, $unit > 0 ? 1 : 0) . ' ' . $units[$unit];
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            MediaItemStatus::Completed->value => 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/30',
            MediaItemStatus::Downloading->value => 'bg-sky-500/15 text-sky-300 ring-sky-500/30',
            MediaItemStatus::Failed->value, MediaItemStatus::Throttled->value =>
                'bg-rose-500/15 text-rose-300 ring-rose-500/30',
            MediaItemStatus::Discovered->value, MediaItemStatus::Indexed->value, MediaItemStatus::Pending->value =>
                'bg-amber-500/15 text-amber-200 ring-amber-500/30',
            MediaItemStatus::Filtered->value => 'bg-slate-500/15 text-slate-400 ring-slate-500/30',
            default => 'bg-slate-500/15 text-slate-300 ring-slate-500/30',
        };
    }
}
