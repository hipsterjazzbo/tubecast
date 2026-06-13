<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaServerType;
use Tempest\Database\IsDatabaseModel;
use Tempest\DateTime\DateTime;
use Tempest\Router\Bindable;

final class MediaServer implements Bindable
{
    use IsDatabaseModel;

    public string $name;
    public MediaServerType $type;
    public string $baseUrl;
    public string $apiToken;
    public string $tubecastVideoRoot;
    public string $tubecastAudioRoot;
    public bool $enabled = true;
    public ?DateTime $lastSyncedAt = null;
    public ?string $lastSyncError = null;
}
