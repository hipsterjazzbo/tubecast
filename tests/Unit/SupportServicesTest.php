<?php

declare(strict_types=1);

use App\Enums\SourceType;
use App\Services\Indexing\IndexingProgress;
use App\Services\YouTube\YouTubeRssUrlBuilder;

describe('IndexingProgress', function (): void {
    it('is inactive when no jobs are running', function (): void {
        $progress = new IndexingProgress(
            active: false,
            pendingCommands: 0,
            fastIndexPending: false,
            fullIndexPending: false,
            episodeCount: 5,
            matchedCount: 3,
            filteredCount: 2,
        );

        expect($progress->active)->toBeFalse()
            ->and($progress->label())->toBe('')
            ->and($progress->widthStyle())->toBe('');
    });

    it('summarizes active yt-dlp indexing without expected total', function (): void {
        $progress = new IndexingProgress(
            active: true,
            pendingCommands: 2,
            fastIndexPending: true,
            fullIndexPending: true,
            episodeCount: 15,
            matchedCount: 10,
            filteredCount: 3,
        );

        expect($progress->label())
            ->toContain('15 episodes indexed')
            ->toContain('10 match')
            ->toContain('3 excluded')
            ->toContain('yt-dlp full index')
            ->and($progress->barClass)->toContain('tc-bar-indeterminate');
    });

    it('shows determinate progress for API indexing', function (): void {
        $progress = new IndexingProgress(
            active: true,
            pendingCommands: 1,
            fastIndexPending: false,
            fullIndexPending: true,
            episodeCount: 40,
            matchedCount: 30,
            filteredCount: 10,
            expectedTotal: 100,
            processedCount: 40,
            usingApi: true,
        );

        expect($progress->label())
            ->toContain('40 / ~100 channel videos')
            ->toContain('YouTube API full index')
            ->and($progress->percent())->toBe(40)
            ->and($progress->widthStyle())->toBe('width: 40%')
            ->and($progress->indeterminate)->toBeFalse();
    });
});

describe('YouTubeRssUrlBuilder', function (): void {
    beforeEach(fn () => $this->builder = new YouTubeRssUrlBuilder());

    it('builds channel RSS URLs', function (): void {
        expect($this->builder->forChannel('UCuNREB5AO14T0t_1kmLqMXA'))
            ->toBe('https://www.youtube.com/feeds/videos.xml?channel_id=UCuNREB5AO14T0t_1kmLqMXA');
    });

    it('extracts channel IDs from URLs', function (): void {
        expect($this->builder->extractChannelId('https://www.youtube.com/channel/UCuNREB5AO14T0t_1kmLqMXA'))
            ->toBe('UCuNREB5AO14T0t_1kmLqMXA');
    });

    it('detects source types from URLs', function (): void {
        expect($this->builder->detectType('https://www.youtube.com/@CriticalRole'))
            ->toBe(SourceType::Channel)
            ->and($this->builder->detectType('https://www.youtube.com/playlist?list=PLabc'))
            ->toBe(SourceType::Playlist)
            ->and($this->builder->detectType('https://www.youtube.com/watch?v=abc'))
            ->toBe(SourceType::Video);
    });

});
