<?php

declare(strict_types=1);

namespace App\Requests;

use App\Enums\DownloadMode;
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

    public function trimmedTitle(): ?string
    {
        $title = trim($this->title);

        return $title !== '' ? $title : null;
    }
}
