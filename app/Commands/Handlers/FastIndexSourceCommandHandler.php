<?php

declare(strict_types=1);

namespace App\Commands\Handlers;

use App\Commands\FastIndexSourceCommand;
use App\Enums\DiscoveredVia;
use App\Enums\MediaItemStatus;
use App\Events\MediaItemIndexed;
use App\Models\MediaItem;
use App\Models\Source;
use App\Services\Core\ModelId;
use App\Services\Source\EpisodeFilterService;
use App\Services\Source\SourceMetadataService;
use App\Services\YouTube\YouTubeRssService;
use Psr\Log\LoggerInterface;
use Tempest\CommandBus\CommandHandler;
use Tempest\DateTime\DateTime;
use Tempest\EventBus\EventBus;

final readonly class FastIndexSourceCommandHandler
{
    public function __construct(
        private YouTubeRssService $rss,
        private SourceMetadataService $metadata,
        private EpisodeFilterService $episodeFilter,
        private EventBus $eventBus,
        private LoggerInterface $logger,
    ) {
    }

    #[CommandHandler]
    public function __invoke(FastIndexSourceCommand $command): void
    {
        $source = Source::findById($command->sourceId);

        if ($source === null || ! $source->enabled || $source->youtubeRssUrl === null) {
            return;
        }

        $this->metadata->ensureTitle($source, allowYtDlp: false);

        try {
            $entries = $this->rss->fetchEntries($source->youtubeRssUrl);
            $source->fastIndexFailures = 0;
        } catch (\Throwable $exception) {
            $source->fastIndexFailures++;
            $source->lastFastIndexedAt = DateTime::now();
            $source->save();
            $this->logger->warning('Fast index failed for source {id}: {message}', [
                'id' => $source->id,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        foreach ($entries as $entry) {
            $existing = MediaItem::select()
                ->where('sourceId = ? AND ytId = ?', ModelId::int($source->id), $entry->videoId)
                ->first();

            if ($existing !== null) {
                continue;
            }

            $item = MediaItem::create(
                sourceId: ModelId::int($source->id),
                ytId: $entry->videoId,
                title: $entry->title,
                publishedAt: $this->publishedAtFromRss($entry->publishedAt),
                status: MediaItemStatus::Discovered,
                discoveredVia: DiscoveredVia::Rss,
            );

            $this->eventBus->dispatch(new MediaItemIndexed(
                mediaItemId: ModelId::int($item->id),
                sourceId: ModelId::int($source->id),
                newlyCreated: true,
            ));
        }

        $source->lastFastIndexedAt = DateTime::now();
        $source->save();
    }

    private function publishedAtFromRss(string $publishedAt): ?DateTime
    {
        if ($publishedAt === '') {
            return null;
        }

        try {
            return DateTime::parse($publishedAt);
        } catch (\Throwable) {
            return null;
        }
    }
}
