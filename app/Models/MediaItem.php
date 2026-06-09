<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DiscoveredVia;
use App\Enums\MediaItemStatus;
use Tempest\Database\IsDatabaseModel;
use Tempest\DateTime\DateTime;
use Tempest\Router\Bindable;

final class MediaItem implements Bindable
{
    use IsDatabaseModel;

    public int $sourceId;
    public string $ytId;
    public ?string $title = null;
    public ?string $description = null;
    public ?int $durationSeconds = null;
    public ?DateTime $publishedAt = null;
    public MediaItemStatus $status = MediaItemStatus::Discovered;
    public DiscoveredVia $discoveredVia = DiscoveredVia::Rss;
    public ?string $filePath = null;
    public ?string $podcastFilePath = null;
    public ?int $podcastFileSize = null;
    public ?string $podcastMime = null;
    public ?string $metadataJson = null;
}
