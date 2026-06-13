<?php

declare(strict_types=1);

namespace App\EventHandlers;

use App\Commands\DownloadMediaCommand;
use App\Events\MediaItemIndexed;
use App\Models\Source;
use App\Services\Source\EpisodeFilterService;
use Tempest\CommandBus\CommandBus;
use Tempest\EventBus\EventHandler;

final readonly class QueueAutoDownloadOnMediaItemIndexed
{
    public function __construct(
        private EpisodeFilterService $episodeFilter,
        private CommandBus $commandBus,
    ) {
    }

    #[EventHandler]
    public function __invoke(MediaItemIndexed $event): void
    {
        if (! $event->newlyCreated) {
            return;
        }

        $source = Source::findById($event->sourceId);

        if ($source === null || ! $this->episodeFilter->shouldAutoDownload($source)) {
            return;
        }

        $this->commandBus->dispatch(new DownloadMediaCommand($event->mediaItemId));
    }
}
