<?php

declare(strict_types=1);

namespace App\Commands;

final readonly class ReapplyEpisodeFiltersCommand
{
    public function __construct(
        public int $sourceId,
    ) {
    }
}
