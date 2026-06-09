<?php

declare(strict_types=1);

use App\Enums\DownloadMode;
use App\Support\SourceFilters;

describe('SourceFilters', function (): void {
    it('round-trips through JSON', function (): void {
        $filters = new SourceFilters(
            downloadMode: DownloadMode::Manual,
            minDurationSeconds: 600,
            maxDurationSeconds: 7200,
            titleRegex: '/Campaign/i',
        );

        $parsed = SourceFilters::fromJson($filters->toJson());

        expect($parsed->downloadMode)->toBe(DownloadMode::Manual)
            ->and($parsed->minDurationSeconds)->toBe(600)
            ->and($parsed->maxDurationSeconds)->toBe(7200)
            ->and($parsed->titleRegex)->toBe('/Campaign/i');
    });

    it('converts form minutes to seconds', function (): void {
        $filters = SourceFilters::fromForm([
            'downloadMode' => 'auto',
            'minDurationMinutes' => '10',
            'maxDurationMinutes' => '120',
            'titleRegex' => '',
        ]);

        expect($filters->minDurationSeconds)->toBe(600)
            ->and($filters->maxDurationSeconds)->toBe(7200)
            ->and($filters->titleRegex)->toBeNull();
    });

    it('returns defaults for empty JSON', function (): void {
        $filters = SourceFilters::fromJson(null);

        expect($filters->downloadMode)->toBe(DownloadMode::Auto)
            ->and($filters->minDurationSeconds)->toBeNull()
            ->and($filters->titleRegex)->toBeNull();
    });

    it('ignores blank title regex in form input', function (): void {
        $filters = SourceFilters::fromForm([
            'downloadMode' => 'auto',
            'titleRegex' => '   ',
        ]);

        expect($filters->titleRegex)->toBeNull();
    });
});
