<?php

declare(strict_types=1);

namespace App\Requests;

use App\Enums\DownloadMode;
use App\Enums\MetadataMode;
use App\Models\Source;
use App\Services\Source\SourceFilters;
use Tempest\Validation\Rules;
use Tempest\Validation\SkipValidation;

trait SourceFormFields
{
    public string $title = '';

    public bool $includeShorts = false;

    public bool $includeLive = false;

    public bool $saveVideo = false;

    public bool $saveAudio = false;

    #[Rules\IsEnum(DownloadMode::class)]
    public DownloadMode $downloadMode = DownloadMode::Auto;

    #[SkipValidation]
    public mixed $minDurationMinutes = null;

    #[SkipValidation]
    public mixed $maxDurationMinutes = null;

    #[SkipValidation]
    public mixed $titleRegex = null;

    #[SkipValidation]
    public mixed $mediaProfileId = null;

    public bool $notifyMediaServer = false;

    #[SkipValidation]
    public mixed $mediaServerLibraryId = null;

    #[Rules\IsEnum(MetadataMode::class)]
    public MetadataMode $metadataMode = MetadataMode::Local;

    #[SkipValidation]
    public mixed $tmdbSeriesId = null;

    #[SkipValidation]
    public mixed $tvdbSeriesId = null;

    public function toSourceFilters(): SourceFilters
    {
        return SourceFilters::fromForm([
            'downloadMode' => $this->downloadMode->value,
            'minDurationMinutes' => $this->minDurationMinutes,
            'maxDurationMinutes' => $this->maxDurationMinutes,
            'titleRegex' => is_string($this->titleRegex) ? $this->titleRegex : '',
        ]);
    }

    public function parsedMediaProfileId(): ?int
    {
        if ($this->mediaProfileId === null || $this->mediaProfileId === '') {
            return null;
        }

        return (int) $this->mediaProfileId;
    }

    public function parsedMediaServerLibraryId(): ?int
    {
        if ($this->mediaServerLibraryId === null || $this->mediaServerLibraryId === '') {
            return null;
        }

        return (int) $this->mediaServerLibraryId;
    }

    public function parsedTmdbSeriesId(): ?int
    {
        if ($this->tmdbSeriesId === null || $this->tmdbSeriesId === '') {
            return null;
        }

        return (int) $this->tmdbSeriesId;
    }

    public function parsedTvdbSeriesId(): ?int
    {
        if ($this->tvdbSeriesId === null || $this->tvdbSeriesId === '') {
            return null;
        }

        return (int) $this->tvdbSeriesId;
    }

    public function trimmedTitle(): ?string
    {
        $title = trim($this->title);

        return $title !== '' ? $title : null;
    }

    public function applyMediaServerSettings(Source $source): void
    {
        $source->notifyMediaServer = $this->notifyMediaServer && $this->parsedMediaServerLibraryId() !== null;
        $source->mediaServerLibraryId = $this->parsedMediaServerLibraryId();
        $source->metadataMode = $this->metadataMode;
        $source->tmdbSeriesId = $this->metadataMode === MetadataMode::Tmdb ? $this->parsedTmdbSeriesId() : null;
        $source->tvdbSeriesId = $this->metadataMode === MetadataMode::Tvdb ? $this->parsedTvdbSeriesId() : null;
    }
}