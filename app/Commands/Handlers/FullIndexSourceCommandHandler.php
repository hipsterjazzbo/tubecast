<?php

declare(strict_types=1);

namespace App\Commands\Handlers;

use App\Commands\FullIndexSourceCommand;
use App\Enums\DiscoveredVia;
use App\Enums\SourceType;
use App\Models\Source;
use App\Services\Core\ThrottleGuard;
use App\Services\Download\YtDlpService;
use App\Services\Source\EpisodeFilterService;
use App\Services\Source\MediaItemIndexingService;
use App\Services\Source\SourceMetadataService;
use App\Services\YouTube\YouTubeDataApiException;
use App\Services\YouTube\YouTubeDataApiService;
use Psr\Log\LoggerInterface;
use Tempest\CommandBus\CommandHandler;
use Tempest\DateTime\DateTime;
use Ytdlphp\Metadata\VideoInfo;

final readonly class FullIndexSourceCommandHandler
{
    public function __construct(
        private YtDlpService $ytDlp,
        private ThrottleGuard $throttle,
        private SourceMetadataService $metadata,
        private EpisodeFilterService $episodeFilter,
        private MediaItemIndexingService $indexing,
        private YouTubeDataApiService $youtubeApi,
        private LoggerInterface $logger,
    ) {
    }

    #[CommandHandler]
    public function __invoke(FullIndexSourceCommand $command): void
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
            $this->indexing->upsertFromVideoInfo($source, $video, DiscoveredVia::YouTubeApi);
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
                $this->indexing->upsertFromVideoInfo($source, $video, DiscoveredVia::YtDlp);
            });
        });

        $source->lastFullIndexedAt = DateTime::now();
        $source->save();
    }
}
