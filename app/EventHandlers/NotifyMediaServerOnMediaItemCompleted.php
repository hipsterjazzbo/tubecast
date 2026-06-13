<?php

declare(strict_types=1);

namespace App\EventHandlers;

use App\Commands\NotifyMediaServerCommand;
use App\Events\MediaItemCompleted;
use App\Models\Source;
use Tempest\CommandBus\CommandBus;
use Tempest\EventBus\EventHandler;

final readonly class NotifyMediaServerOnMediaItemCompleted
{
    public function __construct(
        private CommandBus $commandBus,
    ) {
    }

    #[EventHandler]
    public function __invoke(MediaItemCompleted $event): void
    {
        $source = Source::findById($event->sourceId);

        if ($source === null || ! $source->notifyMediaServer) {
            return;
        }

        $this->commandBus->dispatch(new NotifyMediaServerCommand(
            mediaItemId: $event->mediaItemId,
            sourceId: $event->sourceId,
        ));
    }
}
