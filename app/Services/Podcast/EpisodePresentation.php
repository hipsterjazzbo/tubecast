<?php

declare(strict_types=1);

namespace App\Services\Podcast;

final readonly class EpisodePresentation
{
    public function __construct(
        public string $thumbnailUrl,
        public string $statusLabel,
        public string $statusColorClass,
        public ?string $publishedLabel,
        public string $durationLabel,
        public ?string $fileSizeLabel,
        public bool $showProgressBar,
        public string $progressWidthStyle,
        public bool $progressIndeterminate,
        public ?string $progressLabel,
        public string $progressBarClass,
    ) {
    }
}
