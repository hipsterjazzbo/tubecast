<?php

declare(strict_types=1);

namespace App\Events;

use App\Services\Source\SourceFilters;

final readonly class SourceSettingsUpdated
{
    public function __construct(
        public int $sourceId,
        public bool $previousIncludeShorts,
        public bool $previousIncludeLive,
        public bool $previousSaveVideo,
        public bool $previousSaveAudio,
        public SourceFilters $previousFilters,
        public SourceFilters $newFilters,
        public bool $previousNotifyMediaServer,
        public bool $newNotifyMediaServer,
        public ?int $previousMediaServerLibraryId,
        public ?int $newMediaServerLibraryId,
    ) {
    }
}
