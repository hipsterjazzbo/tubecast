<?php

declare(strict_types=1);

namespace App\Commands\Handlers;

use App\Commands\DownloadMediaCommand;
use App\Commands\EnqueueSourceDownloadsCommand;
use App\Enums\MediaItemStatus;
use App\Models\MediaItem;
use App\Models\Source;
use App\Services\Core\ModelId;
use App\Services\Download\DownloadRecoveryService;
use App\Services\Source\EpisodeFilterService;
use Tempest\CommandBus\CommandBus;
use Tempest\CommandBus\CommandHandler;

final readonly class EnqueueSourceDownloadsCommandHandler
{
    public function __construct(
        private EpisodeFilterService $episodeFilter,
        private DownloadRecoveryService $downloadRecovery,
        private CommandBus $commandBus,
    ) {
    }

    #[CommandHandler]
    public function __invoke(EnqueueSourceDownloadsCommand $command): void
    {
        $source = Source::findById($command->sourceId);

        if ($source === null || ! $source->enabled || ! $this->episodeFilter->shouldAutoDownload($source)) {
            return;
        }

        $sourceId = ModelId::int($source->id);

        foreach (MediaItem::select()->where('sourceId = ?', $sourceId)->all() as $item) {
            if (! $this->needsDownload($source, $item)) {
                continue;
            }

            $this->commandBus->dispatch(new DownloadMediaCommand(ModelId::int($item->id)));
        }
    }

    private function needsDownload(Source $source, MediaItem $item): bool
    {
        if ($item->status === MediaItemStatus::Filtered) {
            return false;
        }

        if ($this->downloadRecovery->hasRequiredMediaOnDisk($source, $item)) {
            return false;
        }

        if ($this->episodeFilter->evaluateItem($source, $item)->matches !== true) {
            return false;
        }

        return $item->status !== MediaItemStatus::Completed;
    }
}
