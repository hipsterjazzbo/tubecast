<?php

declare(strict_types=1);

use App\Services\Source\EpisodeFilterService;
use Tests\Support\Fixtures;

describe('EpisodeFilterService', function (): void {
    beforeEach(function (): void {
        $this->filter = new EpisodeFilterService();
    });

    it('excludes shorts when include shorts is off', function (): void {
        $video = Fixtures::videoInfo([
            'id' => 'abc',
            'title' => 'Clip',
            'duration' => 30,
            'webpage_url' => 'https://www.youtube.com/shorts/abc',
        ]);

        expect($this->filter->matchesEpisode(Fixtures::unsavedSource(), $video))->toBeFalse();
    });

    it('includes shorts when include shorts is on', function (): void {
        $video = Fixtures::videoInfo([
            'id' => 'abc',
            'title' => 'Clip',
            'duration' => 30,
            'webpage_url' => 'https://www.youtube.com/shorts/abc',
        ]);

        expect($this->filter->matchesEpisode(Fixtures::unsavedSource(['includeShorts' => true]), $video))->toBeTrue();
    });

    it('treats unlabeled videos under two minutes as shorts', function (): void {
        $video = Fixtures::videoInfo([
            'id' => 'abc',
            'title' => 'Brief clip',
            'duration' => 90,
            'webpage_url' => 'https://www.youtube.com/watch?v=abc',
            'live_status' => 'not_live',
        ]);

        expect($this->filter->matchesEpisode(Fixtures::unsavedSource(), $video))->toBeFalse()
            ->and($this->filter->rejectReason(Fixtures::unsavedSource(), $video))->toBe('Short');
    });

    it('still treats labeled shorts as shorts regardless of duration', function (): void {
        $video = Fixtures::videoInfo([
            'id' => 'abc',
            'title' => 'Long clip on shorts tab',
            'duration' => 180,
            'webpage_url' => 'https://www.youtube.com/shorts/abc',
            'is_short' => true,
        ]);

        expect($this->filter->matchesEpisode(Fixtures::unsavedSource(), $video))->toBeFalse();
    });

    it('treats unlabeled videos at or above two minutes as long-form', function (): void {
        $video = Fixtures::videoInfo([
            'id' => 'abc',
            'title' => 'Regular episode',
            'duration' => 180,
            'webpage_url' => 'https://www.youtube.com/watch?v=abc',
            'live_status' => 'not_live',
        ]);

        expect($this->filter->matchesEpisode(Fixtures::unsavedSource(), $video))->toBeTrue();
    });

    it('includes regular long-form videos', function (): void {
        $video = Fixtures::videoInfo([
            'id' => 'abc',
            'title' => 'Episode',
            'duration' => 3600,
            'webpage_url' => 'https://www.youtube.com/watch?v=abc',
            'live_status' => 'not_live',
        ]);

        expect($this->filter->matchesEpisode(Fixtures::unsavedSource(), $video))->toBeTrue();
    });

    it('rejects episodes below minimum duration', function (): void {
        $testSource = Fixtures::unsavedSource();
        $testSource->filtersJson = json_encode(['minDurationSeconds' => 600], JSON_THROW_ON_ERROR);

        $video = Fixtures::videoInfo([
            'id' => 'abc',
            'title' => 'Episode',
            'duration' => 300,
            'webpage_url' => 'https://www.youtube.com/watch?v=abc',
            'live_status' => 'not_live',
        ]);

        expect($this->filter->matchesEpisode($testSource, $video))->toBeFalse()
            ->and($this->filter->rejectReason($testSource, $video))->toBe('Too short');
    });

    it('filters episodes by title regex', function (): void {
        $testSource = Fixtures::unsavedSource();
        $testSource->filtersJson = json_encode(['titleRegex' => '/Campaign 3/i'], JSON_THROW_ON_ERROR);

        $match = Fixtures::videoInfo([
            'id' => 'a',
            'title' => 'Critical Role Campaign 3 Episode 1',
            'duration' => 7200,
            'webpage_url' => 'https://www.youtube.com/watch?v=a',
        ]);
        $miss = Fixtures::videoInfo([
            'id' => 'b',
            'title' => 'Fireside Chat',
            'duration' => 7200,
            'webpage_url' => 'https://www.youtube.com/watch?v=b',
        ]);

        expect($this->filter->matchesEpisode($testSource, $match))->toBeTrue()
            ->and($this->filter->matchesEpisode($testSource, $miss))->toBeFalse()
            ->and($this->filter->rejectReason($testSource, $miss))->toBe('Title mismatch');
    });

    it('disables auto-download in manual mode', function (): void {
        $testSource = Fixtures::unsavedSource();
        $testSource->filtersJson = json_encode(['downloadMode' => 'manual'], JSON_THROW_ON_ERROR);

        expect($this->filter->shouldAutoDownload($testSource))->toBeFalse();
    });

    it('enables auto-download when save flags are set', function (): void {
        $testSource = Fixtures::unsavedSource();
        $testSource->saveVideo = true;

        expect($this->filter->shouldAutoDownload($testSource))->toBeTrue();
    });

    it('indexes the full channel without tab suffix', function (): void {
        expect($this->filter->indexUrl(Fixtures::unsavedSource()))
            ->toBe('https://www.youtube.com/@CriticalRole');
    });

    it('reports no custom filters for default long-form-only sources', function (): void {
        expect($this->filter->sourceHasEpisodeFilters(Fixtures::unsavedSource()))->toBeFalse();
    });

    it('reports custom filters when duration or title rules are configured', function (): void {
        $source = Fixtures::unsavedSource();
        $source->filtersJson = json_encode(['minDurationSeconds' => 600], JSON_THROW_ON_ERROR);

        expect($this->filter->sourceHasEpisodeFilters($source))->toBeTrue();
    });
});
