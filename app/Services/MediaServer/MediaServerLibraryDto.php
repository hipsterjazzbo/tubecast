<?php

declare(strict_types=1);

namespace App\Services\MediaServer;

use App\Enums\MediaServerLibraryType;
use App\Models\MediaServerLibrary;

final readonly class MediaServerLibraryDto
{
    public function __construct(
        public string $externalId,
        public string $name,
        public MediaServerLibraryType $libraryType,
        public ?string $remoteRoot,
    ) {
    }
}
