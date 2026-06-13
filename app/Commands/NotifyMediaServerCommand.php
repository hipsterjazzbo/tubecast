<?php

declare(strict_types=1);

namespace App\Commands;

use Tempest\CommandBus\Async;

#[Async]
final readonly class NotifyMediaServerCommand
{
    public function __construct(
        public int $mediaItemId,
        public int $sourceId,
    ) {
    }
}
