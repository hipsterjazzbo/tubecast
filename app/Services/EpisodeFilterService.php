<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DownloadMode;
use App\Enums\SourceType;
use App\Models\MediaItem;
use App\Models\Source;
use App\Support\EpisodeFilterResult;
use App\Support\SourceFilters;
use Ytdlphp\Metadata\VideoInfo;

final class EpisodeFilterService
{
    /** Videos under this length are treated as shorts even without YouTube short labels. */
    private const SHORT_DURATION_CAP = 120;

    /** @var list<string> */
    private const CHANNEL_TABS = ['/videos', '/shorts', '/streams'];

    public function indexUrl(Source $source): string
    {
        if ($source->type !== SourceType::Channel) {
            return $source->url;
        }

        $url = rtrim($source->url, '/');

        foreach (self::CHANNEL_TABS as $tab) {
            if (str_ends_with($url, $tab)) {
                return substr($url, 0, -strlen($tab));
            }
        }

        return $url;
    }

    public function shouldAutoDownload(Source $source): bool
    {
        $filters = SourceFilters::fromSource($source);

        return $filters->downloadMode === DownloadMode::Auto
            && ($source->saveVideo || $source->saveAudio);
    }

    public function matchesEpisode(Source $source, VideoInfo $video): bool
    {
        return $this->rejectReason($source, $video) === null;
    }

    public function matchesEpisodeItem(Source $source, MediaItem $item): bool
    {
        if ($source->type === SourceType::Video) {
            return true;
        }

        if ($item->metadataJson === null || $item->metadataJson === '') {
            return $this->matchesAdvanced(SourceFilters::fromSource($source), null, $item->title ?? '');
        }

        try {
            /** @var array<string, mixed> $raw */
            $raw = json_decode($item->metadataJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        if (! $this->matchesContentType($source, $raw, $item->durationSeconds)) {
            return false;
        }

        return $this->matchesAdvanced(
            SourceFilters::fromSource($source),
            $this->resolveDuration($item->durationSeconds, $raw),
            $item->title ?? '',
        );
    }

    public function evaluateItem(Source $source, MediaItem $item): EpisodeFilterResult
    {
        if ($source->type === SourceType::Video) {
            return new EpisodeFilterResult(true, null);
        }

        if ($item->metadataJson === null || $item->metadataJson === '') {
            return $this->evaluatePartialItem($source, $item);
        }

        $matches = $this->matchesEpisodeItem($source, $item);

        if ($matches) {
            return new EpisodeFilterResult(true, null);
        }

        return new EpisodeFilterResult(false, $this->rejectReasonForItem($source, $item));
    }

    public function sourceHasEpisodeFilters(Source $source): bool
    {
        if ($source->type === SourceType::Video) {
            return false;
        }

        if ($source->includeShorts || $source->includeLive) {
            return true;
        }

        $filters = SourceFilters::fromSource($source);

        return $filters->minDurationSeconds !== null
            || $filters->maxDurationSeconds !== null
            || $filters->titleRegex !== null;
    }

    public function rejectReasonForItem(Source $source, MediaItem $item): ?string
    {
        if ($source->type === SourceType::Video) {
            return null;
        }

        if ($item->metadataJson === null || $item->metadataJson === '') {
            $reason = $this->advancedRejectReason(
                SourceFilters::fromSource($source),
                null,
                $item->title ?? '',
            );

            return $reason ?? 'Needs metadata';
        }

        try {
            /** @var array<string, mixed> $raw */
            $raw = json_decode($item->metadataJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return 'Invalid metadata';
        }

        $contentReason = $this->contentTypeRejectReason($source, $raw, $item->durationSeconds);

        if ($contentReason !== null) {
            return $contentReason;
        }

        return $this->advancedRejectReason(
            SourceFilters::fromSource($source),
            $this->resolveDuration($item->durationSeconds, $raw),
            $item->title ?? '',
        );
    }

    /** @deprecated Use matchesEpisode() */
    public function shouldDownload(Source $source, VideoInfo $video): bool
    {
        return $this->matchesEpisode($source, $video);
    }

    /** Returns why an episode was excluded, or null if it matches all filters. */
    public function rejectReason(Source $source, VideoInfo $video): ?string
    {
        if ($source->type === SourceType::Video) {
            return null;
        }

        $contentReason = $this->contentTypeRejectReason(
            $source,
            $video->raw,
            $video->duration !== null ? (int) $video->duration : null,
        );

        if ($contentReason !== null) {
            return $contentReason;
        }

        return $this->advancedRejectReason(
            SourceFilters::fromSource($source),
            $video->duration !== null ? (int) $video->duration : null,
            $video->title,
        );
    }

    private function evaluatePartialItem(Source $source, MediaItem $item): EpisodeFilterResult
    {
        $filters = SourceFilters::fromSource($source);
        $reason = $this->advancedRejectReason($filters, null, $item->title ?? '');

        if ($reason !== null) {
            return new EpisodeFilterResult(false, $reason);
        }

        return new EpisodeFilterResult(null, null);
    }

    /** @param array<string, mixed> $raw */
    private function contentTypeRejectReason(Source $source, array $raw, ?int $durationSeconds): ?string
    {
        if ($this->isLive($raw) && ! $source->includeLive) {
            return 'Live stream';
        }

        if ($this->isShort($raw, $durationSeconds) && ! $source->includeShorts) {
            return 'Short';
        }

        return null;
    }

    /** @param array<string, mixed> $raw */
    private function matchesContentType(Source $source, array $raw, ?int $durationSeconds): bool
    {
        return $this->contentTypeRejectReason($source, $raw, $durationSeconds) === null;
    }

    private function advancedRejectReason(SourceFilters $filters, ?int $durationSeconds, string $title): ?string
    {
        if ($filters->minDurationSeconds !== null) {
            if ($durationSeconds === null || $durationSeconds < $filters->minDurationSeconds) {
                return 'Too short';
            }
        }

        if ($filters->maxDurationSeconds !== null) {
            if ($durationSeconds === null || $durationSeconds > $filters->maxDurationSeconds) {
                return 'Too long';
            }
        }

        if ($filters->titleRegex !== null && @preg_match($filters->titleRegex, $title) !== 1) {
            return 'Title mismatch';
        }

        return null;
    }

    private function matchesAdvanced(SourceFilters $filters, ?int $durationSeconds, string $title): bool
    {
        return $this->advancedRejectReason($filters, $durationSeconds, $title) === null;
    }

    /** @param array<string, mixed> $raw */
    private function isShort(array $raw, ?int $durationSeconds): bool
    {
        $duration = $this->resolveDuration($durationSeconds, $raw);

        if ($duration !== null && $duration > 0 && $duration < self::SHORT_DURATION_CAP) {
            return true;
        }

        if (($raw['is_short'] ?? false) === true) {
            return true;
        }

        foreach (['url', 'webpage_url', 'original_url'] as $key) {
            $url = $raw[$key] ?? '';

            if (is_string($url) && (str_contains($url, '/shorts/') || str_contains($url, '#shorts'))) {
                return true;
            }
        }

        $width = isset($raw['width']) && is_numeric($raw['width']) ? (int) $raw['width'] : null;
        $height = isset($raw['height']) && is_numeric($raw['height']) ? (int) $raw['height'] : null;

        if ($width !== null && $height !== null && $height > $width) {
            return true;
        }

        return false;
    }

    /** @param array<string, mixed> $raw */
    private function resolveDuration(?int $stored, array $raw): ?int
    {
        if ($stored !== null) {
            return $stored;
        }

        if (! isset($raw['duration']) || ! is_numeric($raw['duration'])) {
            return null;
        }

        return (int) $raw['duration'];
    }

    /** @param array<string, mixed> $raw */
    private function isLive(array $raw): bool
    {
        $liveStatus = $raw['live_status'] ?? null;

        if (in_array($liveStatus, ['is_live', 'was_live', 'post_live'], true)) {
            return true;
        }

        return ($raw['is_live'] ?? false) === true || ($raw['was_live'] ?? false) === true;
    }
}
