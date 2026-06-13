<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MetadataMode;
use App\Enums\SourceType;
use Tempest\Database\IsDatabaseModel;
use Tempest\DateTime\DateTime;
use Tempest\Router\Bindable;

final class Source implements Bindable
{
    use IsDatabaseModel;

    public string $url;
    public SourceType $type = SourceType::Channel;
    public ?string $title = null;
    public bool $includeShorts = false;
    public bool $includeLive = false;
    public ?string $youtubeChannelId = null;
    public ?string $youtubeRssUrl = null;
    public ?int $mediaProfileId = null;
    public ?string $filtersJson = null;
    public ?string $outputTemplate = null;
    public bool $saveVideo = true;
    public bool $saveAudio = false;
    public bool $enabled = true;
    public ?DateTime $lastFastIndexedAt = null;
    public ?DateTime $lastFullIndexedAt = null;
    public int $fastIndexFailures = 0;
    public ?int $indexExpectedTotal = null;
    public ?int $fullIndexProcessedCount = null;
    public bool $notifyMediaServer = false;
    public ?int $mediaServerLibraryId = null;
    public MetadataMode $metadataMode = MetadataMode::Local;
    public ?int $tmdbSeriesId = null;
    public ?int $tvdbSeriesId = null;
}
