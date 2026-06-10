<?php

declare(strict_types=1);

namespace App\Services\Source;

use App\Enums\SourceType;
use App\Models\Feed;
use App\Models\Source;
use App\Services\Core\ModelId;
use App\Services\Download\YtDlpService;
use App\Services\YouTube\YouTubeDataApiService;
use App\Services\YouTube\YouTubeRssService;
use Psr\Log\LoggerInterface;
use Ytdlphp\Metadata\VideoInfo;

final readonly class SourceMetadataService
{
    public function __construct(
        private YouTubeRssService $rss,
        private YouTubeDataApiService $youtubeApi,
        private YtDlpService $ytDlp,
        private LoggerInterface $logger,
    ) {
    }

    /** Sets the source title from RSS or yt-dlp when still unset. Returns true if changed. */
    public function ensureTitle(Source $source, bool $allowYtDlp = true): bool
    {
        if ($source->title !== null && trim($source->title) !== '') {
            return false;
        }

        $title = null;

        if ($source->youtubeRssUrl !== null) {
            $title = $this->rss->fetchFeedTitle($source->youtubeRssUrl);
        }

        if (($title === null || trim($title) === '') && $this->youtubeApi->isConfigured()) {
            $title = $this->youtubeApi->resolveChannelTitle($source->url);
        }

        if (($title === null || trim($title) === '') && $allowYtDlp) {
            $title = $this->titleFromYtDlp($source);
        }

        if ($title === null || trim($title) === '') {
            return false;
        }

        $source->title = trim($title);
        $source->save();
        $this->syncFeedTitle($source);

        return true;
    }

    private function titleFromYtDlp(Source $source): ?string
    {
        try {
            $info = $this->ytDlp->extractPlaylist($source->url);
        } catch (\Throwable $exception) {
            try {
                $info = $this->ytDlp->extractInfo($source->url);
            } catch (\Throwable $fallback) {
                $this->logger->debug('Could not resolve source title for {url}: {message}', [
                    'url' => $source->url,
                    'message' => $fallback->getMessage(),
                ]);

                return null;
            }
        }

        return $this->resolveTitle($source->type, $info);
    }

    private function resolveTitle(SourceType $type, VideoInfo $info): ?string
    {
        $channel = $info->raw['channel'] ?? null;
        $uploader = $info->uploader;

        return match ($type) {
            SourceType::Playlist => $info->title !== '' ? $info->title : null,
            SourceType::Channel => is_string($channel) && $channel !== ''
                ? $channel
                : ($uploader !== null && $uploader !== '' ? $uploader : null),
            SourceType::Video => is_string($channel) && $channel !== ''
                ? $channel
                : ($uploader !== null && $uploader !== '' ? $uploader : ($info->title !== '' ? $info->title : null)),
        };
    }

    private function syncFeedTitle(Source $source): void
    {
        if ($source->title === null || trim($source->title) === '') {
            return;
        }

        $feed = Feed::select()
            ->where('sourceId = ?', ModelId::int($source->id))
            ->first();

        if ($feed === null) {
            return;
        }

        if (! str_starts_with($feed->title, 'YouTube feed #')) {
            return;
        }

        $feed->title = $source->title;
        $feed->save();
    }
}
