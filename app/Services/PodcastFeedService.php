<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Feed;
use App\Models\Source;

final class PodcastFeedService
{
    public function forSource(int $sourceId): ?Feed
    {
        return Feed::select()
            ->where('sourceId = ?', $sourceId)
            ->first();
    }

    public function audioFeedUrl(Feed $feed, string $baseUri): string
    {
        return rtrim($baseUri, '/') . '/feeds/' . $feed->slug . '/audio.xml?token=' . urlencode($feed->token);
    }

    public function videoFeedUrl(Feed $feed, string $baseUri): string
    {
        return rtrim($baseUri, '/') . '/feeds/' . $feed->slug . '/video.xml?token=' . urlencode($feed->token);
    }
}
