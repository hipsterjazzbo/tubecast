<?php

declare(strict_types=1);

namespace App\Services;

final readonly class IndexingProgress
{
    public string $barClass;

    public bool $indeterminate;

    public ?int $indexPercent;

    public function __construct(
        public bool $active,
        public int $pendingCommands,
        public bool $fastIndexPending,
        public bool $fullIndexPending,
        public int $episodeCount,
        public int $matchedCount,
        public int $filteredCount,
        public ?int $expectedTotal = null,
        public ?int $processedCount = null,
        public bool $usingApi = false,
    ) {
        $hasExpectedTotal = $expectedTotal !== null && $expectedTotal > 0;
        $this->indeterminate = $active && ! $hasExpectedTotal;
        $this->barClass = $active
            ? ($this->indeterminate ? 'tc-bar-indeterminate bg-indigo-400' : 'bg-indigo-500 transition-all duration-500 ease-out')
            : 'bg-indigo-500';
        $this->indexPercent = $hasExpectedTotal ? $this->percent() : null;
    }

    public function label(): string
    {
        if (! $this->active) {
            return '';
        }

        $parts = [];

        if ($this->expectedTotal !== null && $this->expectedTotal > 0) {
            $processed = $this->processedCount ?? $this->episodeCount;
            $parts[] = "{$processed} / ~{$this->expectedTotal} channel videos";
        } else {
            $parts[] = "{$this->episodeCount} episodes indexed";
        }

        if ($this->matchedCount > 0 || $this->filteredCount > 0) {
            $parts[] = "{$this->matchedCount} match";
            $parts[] = "{$this->filteredCount} excluded";
        }

        if ($this->fullIndexPending) {
            $parts[] = $this->usingApi
                ? 'YouTube API full index'
                : 'yt-dlp full index (new episodes appear as found)';
        } elseif ($this->fastIndexPending) {
            $parts[] = 'Checking YouTube RSS';
        } else {
            $parts[] = 'Indexing…';
        }

        return implode(' · ', $parts);
    }

    public function widthStyle(): string
    {
        if (! $this->active || $this->indeterminate || $this->expectedTotal === null || $this->expectedTotal <= 0) {
            return '';
        }

        $processed = $this->processedCount ?? $this->episodeCount;
        $percent = min(99, (int) round(($processed / $this->expectedTotal) * 100));

        return 'width: ' . $percent . '%';
    }

    public function percent(): ?int
    {
        if ($this->expectedTotal === null || $this->expectedTotal <= 0) {
            return null;
        }

        $processed = $this->processedCount ?? $this->episodeCount;

        return min(99, (int) round(($processed / $this->expectedTotal) * 100));
    }
}
