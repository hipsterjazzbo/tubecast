<?php

declare(strict_types=1);

namespace App\Commands;

use Tempest\CommandBus\Async;

#[Async]
final readonly class DownloadMediaCommand
{
    public function __construct(
        public int $mediaItemId,
    ) {
    }
}
