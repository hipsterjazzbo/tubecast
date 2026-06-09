<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnclosureMode;
use Tempest\Database\IsDatabaseModel;
use Tempest\Router\Bindable;

final class Feed implements Bindable
{
    use IsDatabaseModel;

    public ?int $sourceId = null;
    public string $slug;
    public string $title;
    public string $token;
    public ?int $maxEpisodes = null;
    public EnclosureMode $enclosureMode = EnclosureMode::Podcast;
    public bool $enabled = true;
}
