<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\DownloadMode;
use App\Enums\DiscoveredVia;
use App\Enums\EnclosureMode;
use App\Enums\MediaItemStatus;
use App\Enums\MediaServerLibraryType;
use App\Enums\MediaServerType;
use App\Enums\SourceType;
use App\Models\Feed;
use App\Models\MediaItem;
use App\Models\MediaServer;
use App\Models\MediaServerLibrary;
use App\Models\Source;
use App\Services\Core\ModelId;
use Ytdlphp\Metadata\VideoInfo;

final class Fixtures
{
    /** In-memory source for unit tests (not persisted). */
    public static function unsavedSource(array $overrides = []): Source
    {
        $source = new Source();
        $source->url = (string) ($overrides['url'] ?? 'https://www.youtube.com/@CriticalRole');
        $source->type = $overrides['type'] ?? SourceType::Channel;
        $source->includeShorts = $overrides['includeShorts'] ?? false;
        $source->includeLive = $overrides['includeLive'] ?? false;
        $source->saveVideo = $overrides['saveVideo'] ?? true;
        $source->saveAudio = $overrides['saveAudio'] ?? false;
        $source->filtersJson = $overrides['filtersJson']
            ?? json_encode(['downloadMode' => DownloadMode::Auto->value], JSON_THROW_ON_ERROR);

        if (isset($overrides['title'])) {
            $source->title = $overrides['title'];
        }

        return $source;
    }

    /** @param array<string, mixed> $overrides */
    public static function criticalRoleSource(array $overrides = []): Source
    {
        return self::source(array_merge([
            'url' => 'https://www.youtube.com/@CriticalRole',
            'title' => 'Critical Role',
            'youtubeChannelId' => 'UCpXBGqwsBkpvcYjsJBQ7LEQ',
            'youtubeRssUrl' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCpXBGqwsBkpvcYjsJBQ7LEQ',
            'saveVideo' => true,
            'saveAudio' => false,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    public static function oculusImperiaSource(array $overrides = []): Source
    {
        return self::source(array_merge([
            'url' => 'https://www.youtube.com/oculusimperia',
            'title' => 'Oculus Imperia',
            'youtubeChannelId' => 'UC1234567890123456789012',
            'youtubeRssUrl' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UC1234567890123456789012',
            'saveVideo' => false,
            'saveAudio' => true,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    public static function source(array $overrides = []): Source
    {
        $source = Source::create(
            url: (string) ($overrides['url'] ?? 'https://www.youtube.com/@CriticalRole'),
            type: $overrides['type'] ?? SourceType::Channel,
            title: $overrides['title'] ?? 'Critical Role',
            youtubeChannelId: $overrides['youtubeChannelId'] ?? 'UCuNREB5AO14T0t_1kmLqMXA',
            youtubeRssUrl: $overrides['youtubeRssUrl'] ?? 'https://www.youtube.com/feeds/videos.xml?channel_id=UCuNREB5AO14T0t_1kmLqMXA',
            includeShorts: $overrides['includeShorts'] ?? false,
            includeLive: $overrides['includeLive'] ?? false,
            saveVideo: $overrides['saveVideo'] ?? true,
            saveAudio: $overrides['saveAudio'] ?? false,
            filtersJson: $overrides['filtersJson'] ?? null,
            enabled: $overrides['enabled'] ?? true,
        );

        $dirty = false;

        foreach (['notifyMediaServer', 'mediaServerLibraryId', 'metadataMode', 'tmdbSeriesId', 'tvdbSeriesId', 'outputTemplate'] as $field) {
            if (array_key_exists($field, $overrides)) {
                $source->{$field} = $overrides[$field];
                $dirty = true;
            }
        }

        if ($dirty) {
            $source->save();
        }

        return $source;
    }

    /** @param array<string, mixed> $overrides */
    public static function feed(Source $source, array $overrides = []): Feed
    {
        $sourceId = ModelId::int($source->id);

        return Feed::create(
            sourceId: $sourceId,
            title: (string) ($overrides['title'] ?? $source->title ?? 'Test feed'),
            token: (string) ($overrides['token'] ?? 'test-feed-token'),
            maxEpisodes: $overrides['maxEpisodes'] ?? 100,
            enclosureMode: $overrides['enclosureMode'] ?? EnclosureMode::Podcast,
            enabled: $overrides['enabled'] ?? true,
        );
    }

    /** @param array<string, mixed> $overrides */
    public static function mediaServer(array $overrides = []): MediaServer
    {
        return MediaServer::create(
            name: (string) ($overrides['name'] ?? 'Test Plex'),
            type: $overrides['type'] ?? MediaServerType::Plex,
            baseUrl: (string) ($overrides['baseUrl'] ?? 'http://plex.test:32400'),
            apiToken: (string) ($overrides['apiToken'] ?? 'test-token'),
            tubecastVideoRoot: (string) ($overrides['tubecastVideoRoot'] ?? '/tmp/tubecast-test/video'),
            tubecastAudioRoot: (string) ($overrides['tubecastAudioRoot'] ?? '/tmp/tubecast-test/audio'),
            enabled: $overrides['enabled'] ?? true,
        );
    }

    /** @param array<string, mixed> $overrides */
    public static function mediaServerLibrary(MediaServer $server, array $overrides = []): MediaServerLibrary
    {
        return MediaServerLibrary::create(
            mediaServerId: ModelId::int($server->id),
            externalId: (string) ($overrides['externalId'] ?? '1'),
            name: (string) ($overrides['name'] ?? 'TV Shows'),
            libraryType: $overrides['libraryType'] ?? MediaServerLibraryType::Tv,
            remoteRoot: $overrides['remoteRoot'] ?? '/mnt/nas/youtube',
            enabled: $overrides['enabled'] ?? true,
        );
    }

    /** @param array<string, mixed> $sourceOverrides */
    /** @param array<string, mixed> $libraryOverrides */
    public static function sourceWithMediaServerLibrary(array $sourceOverrides = [], array $libraryOverrides = []): array
    {
        $server = self::mediaServer();
        $library = self::mediaServerLibrary($server, $libraryOverrides);
        $source = self::source(array_merge($sourceOverrides, [
            'notifyMediaServer' => true,
            'mediaServerLibraryId' => ModelId::int($library->id),
        ]));

        return ['server' => $server, 'library' => $library, 'source' => $source];
    }

    /** @param array<string, mixed> $overrides */
    public static function mediaItem(Source $source, array $overrides = []): MediaItem
    {
        $metadata = $overrides['metadataJson'] ?? json_encode([
            'id' => $overrides['ytId'] ?? 'abc123',
            'title' => $overrides['title'] ?? 'Campaign Episode',
            'duration' => $overrides['durationSeconds'] ?? 7200,
            'webpage_url' => 'https://www.youtube.com/watch?v=' . ($overrides['ytId'] ?? 'abc123'),
            'live_status' => 'not_live',
        ], JSON_THROW_ON_ERROR);

        $item = MediaItem::create(
            sourceId: ModelId::int($source->id),
            ytId: (string) ($overrides['ytId'] ?? 'abc123'),
            title: (string) ($overrides['title'] ?? 'Campaign Episode'),
            durationSeconds: (int) ($overrides['durationSeconds'] ?? 7200),
            status: $overrides['status'] ?? MediaItemStatus::Indexed,
            discoveredVia: $overrides['discoveredVia'] ?? DiscoveredVia::Rss,
            metadataJson: is_string($metadata) ? $metadata : null,
        );

        if (array_key_exists('description', $overrides)) {
            $item->description = $overrides['description'];
        }

        if (array_key_exists('publishedAt', $overrides)) {
            $item->publishedAt = $overrides['publishedAt'];
        }

        if (array_key_exists('filePath', $overrides)) {
            $item->filePath = $overrides['filePath'];
        }

        if (array_key_exists('podcastFilePath', $overrides)) {
            $item->podcastFilePath = $overrides['podcastFilePath'];
        }

        if (array_key_exists('seasonEpisode', $overrides)) {
            $item->seasonEpisode = $overrides['seasonEpisode'];
        }

        if ($item->description !== null
            || $item->publishedAt !== null
            || $item->filePath !== null
            || $item->podcastFilePath !== null
            || $item->seasonEpisode !== null) {
            $item->save();
        }

        return $item;
    }

    /** @param array<string, mixed> $overrides */
    public static function videoInfo(array $overrides = []): VideoInfo
    {
        $raw = array_merge([
            'id' => 'abc123',
            'title' => 'Test Episode',
            'duration' => 3600,
            'webpage_url' => 'https://www.youtube.com/watch?v=abc123',
            'live_status' => 'not_live',
        ], $overrides);

        return new VideoInfo(
            id: (string) $raw['id'],
            title: (string) $raw['title'],
            duration: is_numeric($raw['duration'] ?? null) ? (float) $raw['duration'] : null,
            raw: $raw,
        );
    }
}
