<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaServerLibraryType;
use Tempest\Database\IsDatabaseModel;
use Tempest\Router\Bindable;

final class MediaServerLibrary implements Bindable
{
    use IsDatabaseModel;

    public int $mediaServerId;
    public string $externalId;
    public string $name;
    public MediaServerLibraryType $libraryType = MediaServerLibraryType::Other;
    public ?string $remoteRoot = null;
    public bool $enabled = true;
}
