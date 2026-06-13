<?php

declare(strict_types=1);

namespace App\Services\MediaServer;

use App\Enums\MediaItemStatus;
use App\Events\MediaItemCompleted;
use App\Models\MediaItem;
use App\Models\Source;
use App\Services\Core\ModelId;
use Tempest\EventBus\EventBus;

final class MediaItemCompletionService
{
    public function __construct(
        private EventBus $eventBus,
        private MediaMetadataWriter $metadataWriter,
    ) {
    }

    public function markCompleted(Source $source, MediaItem $item): bool
    {
        if ($item->status === MediaItemStatus::Completed) {
            return false;
        }

        $this->metadataWriter->writeForCompletedItem($source, $item);

        $item->status = MediaItemStatus::Completed;
        $item->save();

        $this->eventBus->dispatch(new MediaItemCompleted(
            mediaItemId: ModelId::int($item->id),
            sourceId: ModelId::int($item->sourceId),
        ));

        return true;
    }
}
