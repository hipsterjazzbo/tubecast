<?php

declare(strict_types=1);

namespace App\Services\Source;

use App\Commands\DownloadMediaCommand;
use App\Enums\DiscoveredVia;
use App\Enums\MediaItemStatus;
use App\Models\MediaItem;
use App\Models\Source;
use App\Services\Core\ModelId;
use Tempest\CommandBus\CommandBus;
use Tempest\DateTime\DateTime;
use Ytdlphp\Metadata\VideoInfo;

final class MediaItemIndexingService
{
    public function __construct(
        private EpisodeFilterService $episodeFilter,
        private CommandBus $commandBus,
    ) {
    }

    public function upsertFromVideoInfo(Source $source, VideoInfo $video, DiscoveredVia $via): void
    {
        $existing = MediaItem::select()
            ->where('sourceId = ? AND ytId = ?', ModelId::int($source->id), $video->id)
            ->first();

        if ($existing !== null) {
            $this->enrichItem($existing, $video);
            $this->applyFilterStatus($source, $existing, $video);
            $existing->save();

            return;
        }

        $matchesFilter = $this->episodeFilter->matchesEpisode($source, $video);

        $item = MediaItem::create(
            sourceId: ModelId::int($source->id),
            ytId: $video->id,
            title: $video->title,
            description: $video->description,
            durationSeconds: $video->duration !== null ? (int) $video->duration : null,
            publishedAt: $this->publishedAtFromVideo($video),
            status: $matchesFilter ? MediaItemStatus::Indexed : MediaItemStatus::Filtered,
            discoveredVia: $via,
        );

        $this->enrichItem($item, $video);
        $item->save();

        if ($matchesFilter && $this->episodeFilter->shouldAutoDownload($source)) {
            $this->commandBus->dispatch(new DownloadMediaCommand(ModelId::int($item->id)));
        }
    }

    public function enrichItem(MediaItem $item, VideoInfo $video): void
    {
        $item->title = $video->title;
        $item->description = $video->description;
        $item->durationSeconds = $video->duration !== null ? (int) $video->duration : null;
        $item->metadataJson = json_encode($video->raw, JSON_THROW_ON_ERROR);

        $publishedAt = $this->publishedAtFromVideo($video);

        if ($publishedAt !== null) {
            $item->publishedAt = $publishedAt;
        }
    }

    public function applyFilterStatus(Source $source, MediaItem $item, VideoInfo $video): void
    {
        if ($this->episodeFilter->matchesEpisode($source, $video)) {
            if ($item->status === MediaItemStatus::Filtered) {
                $item->status = MediaItemStatus::Indexed;
            }

            return;
        }

        if ($item->status !== MediaItemStatus::Completed && $item->status !== MediaItemStatus::Downloading) {
            $item->status = MediaItemStatus::Filtered;
        }
    }

    private function publishedAtFromVideo(VideoInfo $video): ?DateTime
    {
        $publishedAt = $video->raw['published_at'] ?? null;

        if (! is_string($publishedAt) || $publishedAt === '') {
            return null;
        }

        try {
            return DateTime::parse($publishedAt);
        } catch (\Throwable) {
            return null;
        }
    }
}
