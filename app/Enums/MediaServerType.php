<?php

declare(strict_types=1);

namespace App\Enums;

enum MediaServerType: string
{
    case Plex = 'plex';
    case Jellyfin = 'jellyfin';
}
