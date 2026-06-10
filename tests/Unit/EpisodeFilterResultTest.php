<?php

declare(strict_types=1);

use App\Services\Source\EpisodeFilterResult;

describe('EpisodeFilterResult', function (): void {
    it('labels matching episodes', function (): void {
        $result = new EpisodeFilterResult(true, null);

        expect($result->label())->toBe('Matches filter')
            ->and($result->badgeClass())->toContain('emerald')
            ->and($result->rowBorderClass())->toContain('emerald');
    });

    it('labels excluded episodes with reason', function (): void {
        $result = new EpisodeFilterResult(false, 'Too short');

        expect($result->label())->toBe('Excluded: Too short')
            ->and($result->badgeClass())->toContain('slate');
    });

    it('labels pending episodes', function (): void {
        $result = new EpisodeFilterResult(null, null);

        expect($result->label())->toBe('Filter pending')
            ->and($result->rowBorderClass())->toContain('dashed');
    });
});
