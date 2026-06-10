<?php

declare(strict_types=1);

namespace App\Services\Source;

use App\Commands\EnqueueSourceDownloadsCommand;
use App\Commands\FullIndexSourceCommand;
use App\Commands\ReapplyEpisodeFiltersCommand;
use App\Enums\DownloadMode;
use App\Models\Source;
use App\Services\Core\ModelId;
use App\Services\Source\SourceFilters;

final class SourceIndexingTriggers
{
    public function __construct(
        private EpisodeFilterService $episodeFilter,
    ) {
    }

    /** @return list<object> */
    public function commandsAfterSettingsChange(
        Source $source,
        bool $previousIncludeShorts,
        bool $previousIncludeLive,
        bool $previousSaveVideo,
        bool $previousSaveAudio,
        SourceFilters $previousFilters,
        SourceFilters $newFilters,
    ): array {
        if ($source->includeShorts !== $previousIncludeShorts || $source->includeLive !== $previousIncludeLive) {
            return [new FullIndexSourceCommand(ModelId::int($source->id))];
        }

        if ($this->filtersAffectMatching($previousFilters, $newFilters)) {
            return [new ReapplyEpisodeFiltersCommand(ModelId::int($source->id))];
        }

        if ($this->shouldEnqueueDownloads($source, $previousSaveVideo, $previousSaveAudio, $previousFilters, $newFilters)) {
            return [new EnqueueSourceDownloadsCommand(ModelId::int($source->id))];
        }

        return [];
    }

    private function shouldEnqueueDownloads(
        Source $source,
        bool $previousSaveVideo,
        bool $previousSaveAudio,
        SourceFilters $previousFilters,
        SourceFilters $newFilters,
    ): bool {
        if (! $this->episodeFilter->shouldAutoDownload($source)) {
            return false;
        }

        $saveFlagsEnabled = (! $previousSaveAudio && $source->saveAudio)
            || (! $previousSaveVideo && $source->saveVideo);

        $autoModeEnabled = $previousFilters->downloadMode === DownloadMode::Manual
            && $newFilters->downloadMode === DownloadMode::Auto;

        return $saveFlagsEnabled || $autoModeEnabled;
    }

    private function filtersAffectMatching(SourceFilters $previous, SourceFilters $current): bool
    {
        return $previous->minDurationSeconds !== $current->minDurationSeconds
            || $previous->maxDurationSeconds !== $current->maxDurationSeconds
            || $previous->titleRegex !== $current->titleRegex;
    }
}
