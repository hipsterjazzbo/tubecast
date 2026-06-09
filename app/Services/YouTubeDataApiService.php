<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SourceType;
use App\Support\YouTubeChannelInfo;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Ytdlphp\Metadata\VideoInfo;

use function Tempest\env;

final class YouTubeDataApiService
{
    private const API_BASE = 'https://www.googleapis.com/youtube/v3';

    public function __construct(
        private SettingsRepository $settings,
        private YouTubeRssUrlBuilder $rssUrlBuilder,
        private YouTubeChannelPageScraper $pageScraper,
        private Client $http = new Client(),
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    /** @param callable(VideoInfo): void $onVideo */
    public function eachSourceVideo(SourceType $type, string $url, callable $onVideo): int
    {
        return match ($type) {
            SourceType::Channel => $this->eachChannelVideo($url, $onVideo),
            SourceType::Playlist => $this->eachPlaylistVideo($this->requirePlaylistId($url), $onVideo),
            SourceType::Video => $this->eachSingleVideo($this->requireVideoId($url), $onVideo),
        };
    }

    public function expectedVideoCount(SourceType $type, string $url, ?string $channelId = null): ?int
    {
        if ($channelId !== null && $type === SourceType::Channel) {
            return $this->fetchChannelById($channelId)?->videoCount;
        }

        return match ($type) {
            SourceType::Channel => $this->resolveChannel($url)?->videoCount,
            SourceType::Playlist => $this->playlistItemCount($this->requirePlaylistId($url)),
            SourceType::Video => 1,
        };
    }

    public function resolveChannelId(string $url): ?string
    {
        return $this->resolveChannel($url)?->channelId;
    }

    public function resolveChannelById(string $channelId): ?YouTubeChannelInfo
    {
        return $this->fetchChannelById($channelId);
    }

    /** @param callable(VideoInfo): void $onVideo */
    public function eachChannelUploads(string $channelId, callable $onVideo): int
    {
        $channel = $this->fetchChannelById($channelId);

        if ($channel === null) {
            throw new YouTubeDataApiException('YouTube channel not found: ' . $channelId);
        }

        return $this->eachPlaylistVideo($channel->uploadsPlaylistId, $onVideo);
    }

    public function resolveChannelTitle(string $url): ?string
    {
        return $this->resolveChannel($url)?->title;
    }

    public function resolveChannel(string $url): ?YouTubeChannelInfo
    {
        $channelId = $this->rssUrlBuilder->extractChannelId($url);

        if ($channelId !== null) {
            return $this->fetchChannelById($channelId);
        }

        foreach ($this->vanityCandidates($url) as $candidate) {
            $channel = $this->fetchChannelByHandle($candidate);

            if ($channel !== null) {
                return $channel;
            }
        }

        $channelId = $this->pageScraper->resolveChannelId($url);

        if ($channelId !== null) {
            return $this->fetchChannelById($channelId);
        }

        return null;
    }

    /** @param callable(VideoInfo): void $onVideo */
    private function eachChannelVideo(string $url, callable $onVideo): int
    {
        $channel = $this->resolveChannel($url);

        if ($channel === null) {
            throw new YouTubeDataApiException('Could not resolve YouTube channel from URL.');
        }

        return $this->eachPlaylistVideo($channel->uploadsPlaylistId, $onVideo);
    }

    /** @param callable(VideoInfo): void $onVideo */
    private function eachPlaylistVideo(string $playlistId, callable $onVideo): int
    {
        $processed = 0;
        $pageToken = null;

        do {
            $response = $this->request('playlistItems', [
                'part' => 'snippet,contentDetails',
                'playlistId' => $playlistId,
                'maxResults' => '50',
                'pageToken' => $pageToken,
            ]);

            $videoIds = [];

            foreach ($response['items'] ?? [] as $item) {
                $videoId = $item['contentDetails']['videoId']
                    ?? $item['snippet']['resourceId']['videoId']
                    ?? null;

                if (is_string($videoId) && $videoId !== '') {
                    $videoIds[] = $videoId;
                }
            }

            foreach ($this->fetchVideos($videoIds) as $video) {
                $onVideo($video);
                $processed++;
            }

            $pageToken = $response['nextPageToken'] ?? null;
        } while (is_string($pageToken) && $pageToken !== '');

        return $processed;
    }

    /** @param callable(VideoInfo): void $onVideo */
    private function eachSingleVideo(string $videoId, callable $onVideo): int
    {
        foreach ($this->fetchVideos([$videoId]) as $video) {
            $onVideo($video);

            return 1;
        }

        throw new YouTubeDataApiException('Video not found via YouTube Data API.');
    }

    /** @param list<string> $videoIds
     * @return list<VideoInfo>
     */
    private function fetchVideos(array $videoIds): array
    {
        if ($videoIds === []) {
            return [];
        }

        $videos = [];

        foreach (array_chunk($videoIds, 50) as $chunk) {
            $response = $this->request('videos', [
                'part' => 'snippet,contentDetails,liveStreamingDetails',
                'id' => implode(',', $chunk),
            ]);

            foreach ($response['items'] ?? [] as $item) {
                $videos[] = $this->mapVideo($item);
            }
        }

        return $videos;
    }

    private function fetchChannelById(string $channelId): ?YouTubeChannelInfo
    {
        $response = $this->request('channels', [
            'part' => 'snippet,contentDetails,statistics',
            'id' => $channelId,
        ]);

        return $this->mapChannel($response['items'][0] ?? null);
    }

    private function fetchChannelByHandle(string $handle): ?YouTubeChannelInfo
    {
        $response = $this->request('channels', [
            'part' => 'snippet,contentDetails,statistics',
            'forHandle' => ltrim($handle, '@'),
        ]);

        return $this->mapChannel($response['items'][0] ?? null);
    }

    private function playlistItemCount(string $playlistId): ?int
    {
        $response = $this->request('playlists', [
            'part' => 'contentDetails',
            'id' => $playlistId,
        ]);

        $count = $response['items'][0]['contentDetails']['itemCount'] ?? null;

        return is_numeric($count) ? (int) $count : null;
    }

    /** @param array<string, mixed>|null $item */
    private function mapChannel(?array $item): ?YouTubeChannelInfo
    {
        if ($item === null) {
            return null;
        }

        $channelId = $item['id'] ?? null;
        $title = $item['snippet']['title'] ?? null;
        $uploads = $item['contentDetails']['relatedPlaylists']['uploads'] ?? null;
        $videoCount = $item['statistics']['videoCount'] ?? null;

        if (! is_string($channelId) || ! is_string($title) || ! is_string($uploads)) {
            return null;
        }

        return new YouTubeChannelInfo(
            channelId: $channelId,
            title: $title,
            uploadsPlaylistId: $uploads,
            videoCount: is_numeric($videoCount) ? (int) $videoCount : null,
        );
    }

    /** @param array<string, mixed> $item */
    private function mapVideo(array $item): VideoInfo
    {
        $videoId = (string) ($item['id'] ?? '');
        $snippet = $item['snippet'] ?? [];
        $title = is_string($snippet['title'] ?? null) ? $snippet['title'] : '';
        $description = is_string($snippet['description'] ?? null) ? $snippet['description'] : null;
        $duration = $this->parseIso8601Duration(
            is_string($item['contentDetails']['duration'] ?? null) ? $item['contentDetails']['duration'] : null,
        );

        $liveStatus = $this->resolveLiveStatus($item);
        $publishedAt = is_string($snippet['publishedAt'] ?? null) ? $snippet['publishedAt'] : null;

        $raw = [
            'id' => $videoId,
            'title' => $title,
            'description' => $description,
            'duration' => $duration,
            'webpage_url' => 'https://www.youtube.com/watch?v=' . $videoId,
            'live_status' => $liveStatus,
            'is_live' => $liveStatus === 'is_live',
            'was_live' => $liveStatus === 'was_live',
            'upload_date' => $publishedAt !== null ? substr($publishedAt, 0, 10) : null,
            'published_at' => $publishedAt,
        ];

        return new VideoInfo(
            id: $videoId,
            title: $title,
            duration: $duration !== null ? (float) $duration : null,
            description: $description,
            raw: $raw,
        );
    }

    /** @param array<string, mixed> $item */
    private function resolveLiveStatus(array $item): string
    {
        $broadcast = $item['snippet']['liveBroadcastContent'] ?? 'none';
        $details = $item['liveStreamingDetails'] ?? null;

        if ($broadcast === 'live') {
            return 'is_live';
        }

        if (is_array($details)) {
            if (isset($details['actualStartTime']) && ! isset($details['actualEndTime'])) {
                return 'is_live';
            }

            if (isset($details['actualEndTime'])) {
                return 'was_live';
            }
        }

        return 'not_live';
    }

    private function parseIso8601Duration(?string $duration): ?int
    {
        if ($duration === null || $duration === '') {
            return null;
        }

        if (! preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $duration, $matches)) {
            return null;
        }

        $hours = isset($matches[1]) && $matches[1] !== '' ? (int) $matches[1] : 0;
        $minutes = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 0;
        $seconds = isset($matches[3]) && $matches[3] !== '' ? (int) $matches[3] : 0;

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    /** @return list<string> */
    private function vanityCandidates(string $url): array
    {
        $candidates = [];

        if (preg_match('#youtube\.com/@([\w.-]+)#', $url, $match)) {
            $candidates[] = $match[1];
        }

        if (preg_match('#youtube\.com/(?:c|user)/([\w.-]+)#', $url, $match)) {
            $candidates[] = $match[1];
        }

        if (preg_match('#youtube\.com/([\w.-]+)(?:/|\?|$)#', $url, $match)) {
            $slug = $match[1];

            if (! $this->isReservedYouTubePath($slug)) {
                $candidates[] = $slug;
            }
        }

        return array_values(array_unique($candidates));
    }

    private function isReservedYouTubePath(string $slug): bool
    {
        return in_array(strtolower($slug), [
            'watch', 'playlist', 'channel', 'feed', 'results', 'gaming', 'shorts',
            'live', 'premium', 'kids', 'music', 'news', 'sports', 'learning',
            'account', 'creators', 'howyoutubeworks', 'trends', 'jobs', 'about',
            'ads', 'copyright', 'contact', 'press', 'support', 'embed', 'login',
            'logout', 'signup', 'post', 'hashtag', 'redirect', 'share', 'get_video',
        ], true);
    }

    private function requirePlaylistId(string $url): string
    {
        $playlistId = $this->rssUrlBuilder->extractPlaylistId($url);

        if ($playlistId === null) {
            throw new YouTubeDataApiException('Could not resolve playlist ID from URL.');
        }

        return $playlistId;
    }

    private function requireVideoId(string $url): string
    {
        if (preg_match('#[?&]v=([\w-]{11})#', $url, $match)) {
            return $match[1];
        }

        if (preg_match('#youtu\.be/([\w-]{11})#', $url, $match)) {
            return $match[1];
        }

        throw new YouTubeDataApiException('Could not resolve video ID from URL.');
    }

    /** @param array<string, string|null> $params
     * @return array<string, mixed>
     */
    private function request(string $resource, array $params): array
    {
        $key = $this->apiKey();

        if ($key === null) {
            throw new YouTubeDataApiException('YouTube Data API key is not configured.');
        }

        $query = array_filter(
            [...$params, 'key' => $key],
            static fn (?string $value): bool => $value !== null && $value !== '',
        );

        try {
            $response = $this->http->get(self::API_BASE . '/' . $resource, [
                RequestOptions::QUERY => $query,
                RequestOptions::TIMEOUT => 30,
            ]);
        } catch (\Throwable $exception) {
            throw new YouTubeDataApiException(
                'YouTube Data API request failed: ' . $exception->getMessage(),
                previous: $exception,
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        if (isset($payload['error']['message']) && is_string($payload['error']['message'])) {
            throw new YouTubeDataApiException('YouTube Data API error: ' . $payload['error']['message']);
        }

        return $payload;
    }

    private function apiKey(): ?string
    {
        $stored = $this->settings->get('youtubeApiKey');

        if ($stored !== null && $stored !== '') {
            return $stored;
        }

        $env = env('YOUTUBE_API_KEY');

        return is_string($env) && $env !== '' ? $env : null;
    }
}
