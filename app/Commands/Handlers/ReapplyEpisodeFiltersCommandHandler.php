<?php

declare(strict_types=1);

namespace App\Commands\Handlers;

use App\Commands\DownloadMediaCommand;
use App\Commands\ReapplyEpisodeFiltersCommand;
use App\Enums\MediaItemStatus;
use App\Models\MediaItem;
use App\Models\Source;
use App\Services\Core\ModelId;
use App\Services\Source\EpisodeFilterService;
use Tempest\CommandBus\CommandBus;
use Tempest\CommandBus\CommandHandler;

final readonly class ReapplyEpisodeFiltersCommandHandler
{
    public function __construct(
        private EpisodeFilterService $episodeFilter,
        private CommandBus $commandBus,
    ) {
    }

    #[CommandHandler]
    public function __invoke(ReapplyEpisodeFiltersCommand $command): void
    {
        $source = Source::findById($command->sourceId);

        if ($source === null || ! $source->enabled) {
            return;
        }

        $items = MediaItem::select()
            ->where('sourceId = ?', ModelId::int($source->id))
            ->all();

        foreach ($items as $item) {
            $previousStatus = $item->status;
            $result = $this->episodeFilter->evaluateItem($source, $item);

            if ($result->matches === true) {
                if ($item->status === MediaItemStatus::Filtered) {
                    $item->status = MediaItemStatus::Indexed;
                }
            } elseif ($result->matches === false) {
                if ($item->status !== MediaItemStatus::Completed && $item->status !== MediaItemStatus::Downloading) {
                    $item->status = MediaItemStatus::Filtered;
                }
            }

            $item->save();

            if (
                $result->matches === true
                && $previousStatus === MediaItemStatus::Filtered
                && $item->status === MediaItemStatus::Indexed
                && $this->episodeFilter->shouldAutoDownload($source)
            ) {
                $this->commandBus->dispatch(new DownloadMediaCommand(ModelId::int($item->id)));
            }
        }
    }
}
