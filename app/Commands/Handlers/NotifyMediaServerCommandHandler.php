<?php

declare(strict_types=1);

namespace App\Commands\Handlers;

use App\Commands\NotifyMediaServerCommand;
use App\Services\MediaServer\MediaServerException;
use App\Services\MediaServer\MediaServerNotificationService;
use Psr\Log\LoggerInterface;
use Tempest\CommandBus\CommandHandler;

final readonly class NotifyMediaServerCommandHandler
{
    public function __construct(
        private MediaServerNotificationService $notification,
        private LoggerInterface $logger,
    ) {
    }

    #[CommandHandler]
    public function __invoke(NotifyMediaServerCommand $command): void
    {
        try {
            $this->notification->notifyForCompletedItem($command->mediaItemId, $command->sourceId);
        } catch (MediaServerException $exception) {
            $this->logger->warning('Media server notification failed for item {id}: {message}', [
                'id' => $command->mediaItemId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
