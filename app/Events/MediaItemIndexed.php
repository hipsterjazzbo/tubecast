<?php

declare(strict_types=1);

namespace App\Events;

final readonly class MediaItemIndexed
{
    public function __construct(
        public int $mediaItemId,
        public int $sourceId,
        public bool $newlyCreated,
    ) {
    }
}
