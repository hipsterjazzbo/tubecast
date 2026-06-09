<?php

declare(strict_types=1);

namespace App\Models;

use Tempest\Database\IsDatabaseModel;

final class MediaProfile
{
    use IsDatabaseModel;

    public string $name;
    public bool $audioOnly = false;
    public string $formatSelector = 'bestvideo+bestaudio/best';
    public ?string $mergeFormat = null;
    public ?int $maxHeight = null;
    public ?string $subtitleLanguages = null;
    public bool $sponsorblockRemove = false;
    public int $podcastBitrateKbps = 96;
}
