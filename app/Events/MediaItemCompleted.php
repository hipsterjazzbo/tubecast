<?php

declare(strict_types=1);

namespace App\Events;

final readonly class MediaItemCompleted
{
    public function __construct(
        public int $mediaItemId,
        public int $sourceId,
    ) {
    }
}
