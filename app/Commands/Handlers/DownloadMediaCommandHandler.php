<?php

declare(strict_types=1);

namespace App\Commands\Handlers;

use App\Commands\DownloadMediaCommand;
use App\Enums\MediaItemStatus;
use App\Models\MediaItem;
use App\Models\MediaProfile;
use App\Models\Source;
use App\Services\Core\ModelId;
use App\Services\Core\ThrottleGuard;
use App\Services\Download\DownloadRecoveryService;
use App\Services\Download\OutputPathBuilder;
use App\Services\Download\YtDlpService;
use App\Services\Podcast\PodcastVariantService;
use App\Services\Source\EpisodeFilterService;
use App\Services\Source\MediaItemIndexingService;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tempest\CommandBus\CommandHandler;
use Throwable;

final readonly class DownloadMediaCommandHandler
{
    public function __construct(
        private YtDlpService $ytDlp,
        private ThrottleGuard $throttle,
        private OutputPathBuilder $paths,
        private PodcastVariantService $podcast,
        private EpisodeFilterService $episodeFilter,
        private MediaItemIndexingService $indexing,
        private DownloadRecoveryService $downloadRecovery,
        private LoggerInterface $logger,
    ) {
    }

    #[CommandHandler]
    public function __invoke(DownloadMediaCommand $command): void
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
            $profile = $this->profileFor($source);

            if ($this->downloadRecovery->finalizeFromDisk($source, $item, $profile)) {
                return;
            }
        }

        if ($item->status === MediaItemStatus::Downloading) {
            $profile = $this->profileFor($source);

            if ($this->downloadRecovery->prepareInterruptedDownload($source, $item, $profile)) {
                return;
            }
        }

        if ($this->isAlreadyCompleted($source, $item)) {
            return;
        }

        $profile = $this->profileFor($source);

        try {
            $this->throttle->run(function () use ($source, $profile, $item): void {
                $url = 'https://www.youtube.com/watch?v=' . $item->ytId;
                $info = $this->ytDlp->extractInfo($url);
                $this->indexing->enrichItem($item, $info);

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

                    if (! $this->applyPodcastFile($item, $source)) {
                        throw new RuntimeException(
                            sprintf('Podcast file for %s was not found after audio download', $item->ytId),
                        );
                    }

                    $item->filePath = null;
                } else {
                    $this->ytDlp->forSource($source, $profile)->download($url);
                    $item->filePath = $this->paths->findVideoFile($item->ytId);

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
        } catch (Throwable $exception) {
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

    private function profileFor(Source $source): ?MediaProfile
    {
        return $source->mediaProfileId !== null
            ? MediaProfile::findById($source->mediaProfileId)
            : null;
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

    private function applyPodcastFile(MediaItem $item, Source $source): bool
    {
        $path = $this->paths->findAudioFile(ModelId::int($source->id), $item->ytId);

        if ($path === null) {
            return false;
        }

        $item->podcastFilePath = $path;
        $item->podcastFileSize = filesize($path) ?: null;
        $item->podcastMime = 'audio/mp4';

        return true;
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

    private function isThrottled(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'sign in to confirm')
            || str_contains($message, 'bot')
            || str_contains($message, '429');
    }
}
