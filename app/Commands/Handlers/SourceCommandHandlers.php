<?php

declare(strict_types=1);

namespace App\Commands\Handlers;

use App\Commands\DownloadMediaCommand;
use App\Commands\EnqueueSourceDownloadsCommand;
use App\Commands\FastIndexSourceCommand;
use App\Commands\FullIndexSourceCommand;
use App\Commands\ReapplyEpisodeFiltersCommand;
use App\Enums\DiscoveredVia;
use App\Enums\MediaItemStatus;
use App\Enums\SourceType;
use App\Models\MediaItem;
use App\Models\MediaProfile;
use App\Models\Source;
use App\Support\ModelId;
use App\Services\DownloadRecoveryService;
use App\Services\EpisodeFilterService;
use App\Services\OutputPathBuilder;
use App\Services\PodcastFeedService;
use App\Services\PodcastVariantService;
use App\Services\SourceMetadataService;
use App\Services\ThrottleGuard;
use App\Services\YouTubeDataApiException;
use App\Services\YouTubeDataApiService;
use App\Services\YouTubeRssService;
use App\Services\YtDlpService;
use Psr\Log\LoggerInterface;
use Tempest\CommandBus\CommandBus;
use Tempest\CommandBus\CommandHandler;
use Tempest\DateTime\DateTime;
use Ytdlphp\Metadata\VideoInfo;

final readonly class SourceCommandHandlers
{
    public function __construct(
        private YouTubeRssService $rss,
        private YtDlpService $ytDlp,
        private ThrottleGuard $throttle,
        private OutputPathBuilder $paths,
        private PodcastVariantService $podcast,
        private PodcastFeedService $feeds,
        private SourceMetadataService $metadata,
        private EpisodeFilterService $episodeFilter,
        private YouTubeDataApiService $youtubeApi,
        private DownloadRecoveryService $downloadRecovery,
        private LoggerInterface $logger,
        private CommandBus $commandBus,
    ) {
    }

    #[CommandHandler]
    public function handleFastIndex(FastIndexSourceCommand $command): void
    {
        $source = Source::findById($command->sourceId);

        if ($source === null || ! $source->enabled || $source->youtubeRssUrl === null) {
            return;
        }

        $this->metadata->ensureTitle($source, allowYtDlp: false);

        try {
            $entries = $this->rss->fetchEntries($source->youtubeRssUrl);
            $source->fastIndexFailures = 0;
        } catch (\Throwable $exception) {
            $source->fastIndexFailures++;
            $source->lastFastIndexedAt = DateTime::now();
            $source->save();
            $this->logger->warning('Fast index failed for source {id}: {message}', [
                'id' => $source->id,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        foreach ($entries as $entry) {
            $existing = MediaItem::select()
                ->where('sourceId = ? AND ytId = ?', ModelId::int($source->id), $entry->videoId)
                ->first();

            if ($existing !== null) {
                continue;
            }

            $item = MediaItem::create(
                sourceId: ModelId::int($source->id),
                ytId: $entry->videoId,
                title: $entry->title,
                publishedAt: $entry->publishedAt !== '' ? DateTime::parse($entry->publishedAt) : null,
                status: MediaItemStatus::Discovered,
                discoveredVia: DiscoveredVia::Rss,
            );

            if ($this->episodeFilter->shouldAutoDownload($source)) {
                $this->commandBus->dispatch(new DownloadMediaCommand(ModelId::int($item->id)));
            }
        }

        $source->lastFastIndexedAt = DateTime::now();
        $source->save();
    }

    #[CommandHandler]
    public function handleFullIndex(FullIndexSourceCommand $command): void
    {
        $source = Source::findById($command->sourceId);

        if ($source === null || ! $source->enabled) {
            return;
        }

        $this->metadata->ensureTitle($source);

        if ($this->youtubeApi->isConfigured()) {
            try {
                $this->fullIndexViaYouTubeApi($source);

                return;
            } catch (YouTubeDataApiException $exception) {
                $this->logger->warning('YouTube API full index failed for source {id}, falling back to yt-dlp: {message}', [
                    'id' => $source->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->fullIndexViaYtDlp($source);
    }

    private function fullIndexViaYouTubeApi(Source $source): void
    {
        $channelId = $source->youtubeChannelId;

        if ($channelId === null && $source->type === SourceType::Channel) {
            $channelId = $this->youtubeApi->resolveChannelId($source->url);

            if ($channelId !== null) {
                $source->youtubeChannelId = $channelId;
            }
        }

        $expected = $this->youtubeApi->expectedVideoCount($source->type, $source->url, $channelId);
        $source->indexExpectedTotal = $expected;
        $source->fullIndexProcessedCount = 0;
        $source->save();

        $processed = 0;

        $indexVideos = function (VideoInfo $video) use ($source, &$processed): void {
            $this->upsertFromVideoInfo($source, $video, DiscoveredVia::YouTubeApi);
            $processed++;

            if ($processed % 25 === 0) {
                $source->fullIndexProcessedCount = $processed;
                $source->save();
            }
        };

        if ($channelId !== null && $source->type === SourceType::Channel) {
            $this->youtubeApi->eachChannelUploads($channelId, $indexVideos);
        } else {
            $this->youtubeApi->eachSourceVideo($source->type, $source->url, $indexVideos);
        }

        $source->fullIndexProcessedCount = $processed;
        $source->lastFullIndexedAt = DateTime::now();
        $source->save();
    }

    private function fullIndexViaYtDlp(Source $source): void
    {
        $source->indexExpectedTotal = null;
        $source->fullIndexProcessedCount = null;
        $source->save();

        $indexUrl = $this->episodeFilter->indexUrl($source);

        $this->throttle->run(function () use ($source, $indexUrl): void {
            $this->ytDlp->eachVideo($indexUrl, function (VideoInfo $video) use ($source): void {
                $this->upsertFromVideoInfo($source, $video, DiscoveredVia::YtDlp);
            });
        });

        $source->lastFullIndexedAt = DateTime::now();
        $source->save();
    }

    #[CommandHandler]
    public function handleReapplyEpisodeFilters(ReapplyEpisodeFiltersCommand $command): void
    {
        $source = Source::findById($command->sourceId);

        if ($source === null || ! $source->enabled) {
            return;
        }

        $items = MediaItem::select()
            ->where('sourceId = ?', ModelId::int($source->id))
            ->all();

        foreach ($items as $item) {
            $previousStatus = $item->status;
            $result = $this->episodeFilter->evaluateItem($source, $item);

            if ($result->matches === true) {
                if ($item->status === MediaItemStatus::Filtered) {
                    $item->status = MediaItemStatus::Indexed;
                }
            } elseif ($result->matches === false) {
                if ($item->status !== MediaItemStatus::Completed && $item->status !== MediaItemStatus::Downloading) {
                    $item->status = MediaItemStatus::Filtered;
                }
            }

            $item->save();

            if (
                $result->matches === true
                && $previousStatus === MediaItemStatus::Filtered
                && $item->status === MediaItemStatus::Indexed
                && $this->episodeFilter->shouldAutoDownload($source)
            ) {
                $this->commandBus->dispatch(new DownloadMediaCommand(ModelId::int($item->id)));
            }
        }
    }

    #[CommandHandler]
    public function handleEnqueueDownloads(EnqueueSourceDownloadsCommand $command): void
    {
        $source = Source::findById($command->sourceId);

        if ($source === null || ! $source->enabled || ! $this->episodeFilter->shouldAutoDownload($source)) {
            return;
        }

        $sourceId = ModelId::int($source->id);

        foreach (MediaItem::select()->where('sourceId = ?', $sourceId)->all() as $item) {
            if (! $this->needsDownload($source, $item)) {
                continue;
            }

            $this->commandBus->dispatch(new DownloadMediaCommand(ModelId::int($item->id)));
        }
    }

    #[CommandHandler]
    public function handleDownload(DownloadMediaCommand $command): void
    {
        $item = MediaItem::findById($command->mediaItemId);

        if ($item === null) {
            return;
        }

        $source = Source::findById($item->sourceId);

        if ($source === null || ! $source->enabled) {
            return;
        }

        if (! $source->saveVideo && ! $source->saveAudio) {
            return;
        }

        if ($this->downloadRecovery->hasRequiredMediaOnDisk($source, $item)) {
            $profile = $source->mediaProfileId !== null
                ? MediaProfile::findById($source->mediaProfileId)
                : null;

            if ($this->downloadRecovery->finalizeFromDisk($source, $item, $profile)) {
                return;
            }
        }

        if ($item->status === MediaItemStatus::Downloading) {
            $profile = $source->mediaProfileId !== null
                ? MediaProfile::findById($source->mediaProfileId)
                : null;

            if ($this->downloadRecovery->prepareInterruptedDownload($source, $item, $profile)) {
                return;
            }
        }

        if ($this->isAlreadyCompleted($source, $item)) {
            return;
        }

        $profile = $source->mediaProfileId !== null
            ? MediaProfile::findById($source->mediaProfileId)
            : null;

        try {
            $this->throttle->run(function () use ($source, $profile, $item): void {
                $url = 'https://www.youtube.com/watch?v=' . $item->ytId;
                $info = $this->ytDlp->extractInfo($url);
                $this->enrichItem($item, $info);

                if (! $this->episodeFilter->matchesEpisode($source, $info)) {
                    $item->status = MediaItemStatus::Filtered;
                    $item->save();

                    return;
                }

                if ($this->generatePodcastFromExistingVideo($source, $item, $profile)) {
                    $item->status = MediaItemStatus::Completed;
                    $item->save();

                    return;
                }

                $item->status = MediaItemStatus::Downloading;
                $item->save();

                $audioOnly = $source->saveAudio && ! $source->saveVideo;

                if ($audioOnly) {
                    $this->ytDlp->forAudioDownload($source, $profile)->download($url);
                    $this->applyPodcastFile($item, $source);
                    $item->filePath = null;
                } else {
                    $this->ytDlp->forSource($source, $profile)->download($url);
                    $item->filePath = $this->paths->findDownloadedFile($item->ytId);

                    if ($source->saveAudio) {
                        $this->podcast->generate($item, $profile);
                    }

                    if (! $source->saveAudio && $item->podcastFilePath !== null && is_file($item->podcastFilePath)) {
                        unlink($item->podcastFilePath);
                        $item->podcastFilePath = null;
                        $item->podcastFileSize = null;
                        $item->podcastMime = null;
                    }
                }

                $item->status = MediaItemStatus::Completed;
            });
        } catch (\Throwable $exception) {
            $item->status = $this->isThrottled($exception)
                ? MediaItemStatus::Throttled
                : MediaItemStatus::Failed;
            $this->logger->error('Download failed for {ytId}: {message}', [
                'ytId' => $item->ytId,
                'message' => $exception->getMessage(),
            ]);
        }

        $item->save();
    }

    private function upsertFromVideoInfo(Source $source, VideoInfo $video, DiscoveredVia $via): void
    {
        $existing = MediaItem::select()
            ->where('sourceId = ? AND ytId = ?', ModelId::int($source->id), $video->id)
            ->first();

        if ($existing !== null) {
            $this->enrichItem($existing, $video);
            $this->applyFilterStatus($source, $existing, $video);
            $existing->save();

            return;
        }

        $matchesFilter = $this->episodeFilter->matchesEpisode($source, $video);

        $item = MediaItem::create(
            sourceId: ModelId::int($source->id),
            ytId: $video->id,
            title: $video->title,
            description: $video->description,
            durationSeconds: $video->duration !== null ? (int) $video->duration : null,
            publishedAt: $this->publishedAtFromVideo($video),
            status: $matchesFilter ? MediaItemStatus::Indexed : MediaItemStatus::Filtered,
            discoveredVia: $via,
        );

        $this->enrichItem($item, $video);
        $item->save();

        if ($matchesFilter && $this->episodeFilter->shouldAutoDownload($source)) {
            $this->commandBus->dispatch(new DownloadMediaCommand(ModelId::int($item->id)));
        }
    }

    private function applyFilterStatus(Source $source, MediaItem $item, VideoInfo $video): void
    {
        if ($this->episodeFilter->matchesEpisode($source, $video)) {
            if ($item->status === MediaItemStatus::Filtered) {
                $item->status = MediaItemStatus::Indexed;
            }

            return;
        }

        if ($item->status !== MediaItemStatus::Completed && $item->status !== MediaItemStatus::Downloading) {
            $item->status = MediaItemStatus::Filtered;
        }
    }

    private function enrichItem(MediaItem $item, VideoInfo $video): void
    {
        $item->title = $video->title;
        $item->description = $video->description;
        $item->durationSeconds = $video->duration !== null ? (int) $video->duration : null;
        $item->metadataJson = json_encode($video->raw, JSON_THROW_ON_ERROR);

        $publishedAt = $this->publishedAtFromVideo($video);

        if ($publishedAt !== null) {
            $item->publishedAt = $publishedAt;
        }
    }

    private function publishedAtFromVideo(VideoInfo $video): ?DateTime
    {
        $publishedAt = $video->raw['published_at'] ?? null;

        if (! is_string($publishedAt) || $publishedAt === '') {
            return null;
        }

        try {
            return DateTime::parse($publishedAt);
        } catch (\Throwable) {
            return null;
        }
    }

    private function needsDownload(Source $source, MediaItem $item): bool
    {
        if ($item->status === MediaItemStatus::Filtered) {
            return false;
        }

        if ($this->downloadRecovery->hasRequiredMediaOnDisk($source, $item)) {
            return false;
        }

        if ($this->episodeFilter->evaluateItem($source, $item)->matches !== true) {
            return false;
        }

        return $item->status !== MediaItemStatus::Completed;
    }

    private function generatePodcastFromExistingVideo(Source $source, MediaItem $item, ?MediaProfile $profile): bool
    {
        if (! $source->saveAudio || ! $source->saveVideo) {
            return false;
        }

        if ($item->filePath === null || ! is_file($item->filePath)) {
            return false;
        }

        if ($item->podcastFilePath !== null && is_file($item->podcastFilePath)) {
            return false;
        }

        $this->podcast->generate($item, $profile);

        return $item->podcastFilePath !== null && is_file($item->podcastFilePath);
    }

    private function applyPodcastFile(MediaItem $item, Source $source): void
    {
        $path = $this->paths->findPodcastFile(ModelId::int($source->id), $item->ytId);

        if ($path === null) {
            return;
        }

        $item->podcastFilePath = $path;
        $item->podcastFileSize = filesize($path) ?: null;
        $item->podcastMime = 'audio/mp4';
    }

    private function isAlreadyCompleted(Source $source, MediaItem $item): bool
    {
        if ($item->status !== MediaItemStatus::Completed) {
            return false;
        }

        $videoOk = ! $source->saveVideo || ($item->filePath !== null && is_file($item->filePath));
        $audioOk = ! $source->saveAudio || ($item->podcastFilePath !== null && is_file($item->podcastFilePath));

        return $videoOk && $audioOk;
    }

    private function isThrottled(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'sign in to confirm')
            || str_contains($message, 'bot')
            || str_contains($message, '429');
    }
}
