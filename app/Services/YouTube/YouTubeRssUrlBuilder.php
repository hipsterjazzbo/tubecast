<?php

declare(strict_types=1);

namespace App\Services\YouTube;

use App\Enums\SourceType;

final class YouTubeRssUrlBuilder
{
    private const BASE = 'https://www.youtube.com/feeds/videos.xml';

    public function forChannel(string $channelId): string
    {
        return self::BASE . '?channel_id=' . $channelId;
    }

    public function forPlaylist(string $playlistId): string
    {
        return self::BASE . '?playlist_id=' . $playlistId;
    }

    public function extractChannelId(string $url): ?string
    {
        if (preg_match('#/channel/(UC[\w-]{22})#', $url, $m)) {
            return $m[1];
        }

        if (preg_match('#[?&]channel_id=(UC[\w-]{22})#', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    public function extractPlaylistId(string $url): ?string
    {
        if (preg_match('#[?&]list=([^&]+)#', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    public function detectType(string $url): SourceType
    {
        if (str_contains($url, 'playlist?list=') || preg_match('#[?&]list=#', $url)) {
            return SourceType::Playlist;
        }

        if (str_contains($url, '/watch?v=') && ! preg_match('#[?&]list=#', $url)) {
            return SourceType::Video;
        }

        return SourceType::Channel;
    }
}
