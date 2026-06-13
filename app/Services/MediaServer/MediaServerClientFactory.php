<?php

declare(strict_types=1);

namespace App\Services\MediaServer;

use App\Enums\MediaServerType;
use App\Models\MediaServer;
use RuntimeException;

final class MediaServerClientFactory
{
    public function __construct(
        private PlexClient $plex,
        private JellyfinClient $jellyfin,
    ) {
    }

    public function for(MediaServer $server): MediaServerClient
    {
        return match ($server->type) {
            MediaServerType::Plex => $this->plex,
            MediaServerType::Jellyfin => $this->jellyfin,
        };
    }

    public function forType(MediaServerType $type): MediaServerClient
    {
        return match ($type) {
            MediaServerType::Plex => $this->plex,
            MediaServerType::Jellyfin => $this->jellyfin,
        };
    }
}
