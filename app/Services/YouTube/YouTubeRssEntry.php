<?php

declare(strict_types=1);

namespace App\Services\YouTube;

final readonly class YouTubeRssEntry
{
    public function __construct(
        public string $videoId,
        public string $title,
        public string $publishedAt,
        public string $url,
    ) {
    }
}
