<?php

declare(strict_types=1);

namespace App\Services\YouTube;

final readonly class YouTubeChannelInfo
{
    public function __construct(
        public string $channelId,
        public string $title,
        public string $uploadsPlaylistId,
        public ?int $videoCount,
    ) {
    }
}
