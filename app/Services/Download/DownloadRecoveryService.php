<?php

declare(strict_types=1);

namespace App\Services\Download;

use App\Commands\EnqueueSourceDownloadsCommand;
use App\Enums\MediaItemStatus;
use App\Models\MediaItem;
use App\Models\MediaProfile;
use App\Models\Source;
use App\Services\Core\ModelId;
use App\Services\MediaServer\MediaItemCompletionService;
use App\Services\Podcast\PodcastVariantService;
use Tempest\CommandBus\CommandBus;
use Tempest\Log\Logger;

final class DownloadRecoveryService
{
    public function __construct(
        private OutputPathBuilder $paths,
        private PodcastVariantService $podcast,
        private CommandBus $commandBus,
        private MediaItemCompletionService $completion,
        private Logger $logger,
    ) {
    }

    /** @return array{finalized: int, enqueued: int} */
    public function recoverAll(): array
    {
        $finalized = 0;

        foreach (MediaItem::select()->where('status = ?', MediaItemStatus::Downloading->value)->all() as $item) {
            $source = Source::findById($item->sourceId);

            if ($source === null || ! $source->enabled) {
                continue;
            }

            $profile = $source->mediaProfileId !== null
                ? MediaProfile::findById($source->mediaProfileId)
                : null;

            if ($this->finalizeFromDisk($source, $item, $profile)) {
                $finalized++;
            }
        }

        $enqueued = 0;

        foreach (Source::select()->where('enabled = 1')->all() as $source) {
            if (! $source->saveVideo && ! $source->saveAudio) {
                continue;
            }

            $needsQueue = MediaItem::count()
                ->where('sourceId = ? AND status IN (?, ?)', ModelId::int($source->id), MediaItemStatus::Indexed->value, MediaItemStatus::Downloading->value)
                ->execute() > 0;

            if (! $needsQueue) {
                continue;
            }

            $this->commandBus->dispatch(new EnqueueSourceDownloadsCommand(ModelId::int($source->id)));
            $enqueued++;
        }

        if ($finalized > 0 || $enqueued > 0) {
            $this->logger->info('Download recovery: {finalized} finalized, {enqueued} sources re-queued', [
                'finalized' => $finalized,
                'enqueued' => $enqueued,
            ]);
        }

        return ['finalized' => $finalized, 'enqueued' => $enqueued];
    }

    public function hasRequiredMediaOnDisk(Source $source, MediaItem $item): bool
    {
        $videoOk = ! $source->saveVideo
            || ($item->filePath !== null && is_file($item->filePath))
            || $this->paths->findVideoFile($item->ytId) !== null;

        $audioOk = ! $source->saveAudio
            || ($item->podcastFilePath !== null && is_file($item->podcastFilePath))
            || $this->paths->findAudioFile(ModelId::int($source->id), $item->ytId) !== null;

        return $videoOk && $audioOk;
    }

    public function finalizeFromDisk(Source $source, MediaItem $item, ?MediaProfile $profile): bool
    {
        if (! $this->hasRequiredMediaOnDisk($source, $item)) {
            return false;
        }

        $audioOnly = $source->saveAudio && ! $source->saveVideo;

        if ($audioOnly) {
            $this->applyPodcastFile($item, $source);
            $item->filePath = null;
        } else {
            if ($item->filePath === null || ! is_file($item->filePath)) {
                $item->filePath = $this->paths->findVideoFile($item->ytId);
            }

            if ($source->saveAudio) {
                if ($item->podcastFilePath === null || ! is_file($item->podcastFilePath)) {
                    $this->generatePodcastFromExistingVideo($source, $item, $profile);
                }
            }
        }

        if (! $this->hasRequiredMediaOnDisk($source, $item)) {
            return false;
        }

        return $this->completion->markCompleted($source, $item);
    }

    public function prepareInterruptedDownload(Source $source, MediaItem $item, ?MediaProfile $profile): bool
    {
        if ($this->finalizeFromDisk($source, $item, $profile)) {
            return true;
        }

        if ($item->status !== MediaItemStatus::Downloading) {
            return false;
        }

        $item->status = MediaItemStatus::Indexed;
        $item->save();

        return false;
    }

    private function applyPodcastFile(MediaItem $item, Source $source): void
    {
        $path = $this->paths->findAudioFile(ModelId::int($source->id), $item->ytId);

        if ($path === null) {
            return;
        }

        $item->podcastFilePath = $path;
        $item->podcastFileSize = filesize($path) ?: null;
        $item->podcastMime = str_ends_with($path, '.webm') ? 'audio/webm' : 'audio/mp4';
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
            return true;
        }

        $this->podcast->generate($item, $profile);

        return $item->podcastFilePath !== null && is_file($item->podcastFilePath);
    }
}
