<?php

declare(strict_types=1);

use App\Enums\MediaItemStatus;
use App\Models\MediaItem;
use App\Services\DownloadProgress;
use App\Support\MediaItemPresenter;

describe('MediaItemPresenter', function (): void {
    it('formats duration for long episodes', function (): void {
        expect(MediaItemPresenter::formatDuration(3661))->toBe('1:01:01');
    });

    it('formats duration for short episodes', function (): void {
        expect(MediaItemPresenter::formatDuration(125))->toBe('2:05');
    });

    it('formats file sizes', function (): void {
        expect(MediaItemPresenter::formatFileSize(1536))->toBe('1.5 KB');
    });

    it('falls back to YouTube thumbnail URL', function (): void {
        expect(MediaItemPresenter::thumbnail(null, 'dQw4w9WgXcQ'))
            ->toBe('https://i.ytimg.com/vi/dQw4w9WgXcQ/mqdefault.jpg');
    });

    it('builds presentation for completed items', function (): void {
        $item = new MediaItem();
        $item->ytId = 'abc123';
        $item->title = 'Episode';
        $item->status = MediaItemStatus::Completed;
        $item->durationSeconds = 3600;

        $presentation = MediaItemPresenter::for($item, new DownloadProgress(
            active: false,
            percent: 100,
            bytesReceived: null,
            bytesTotal: null,
            indeterminate: false,
        ));

        expect($presentation->statusLabel)->toBe('Completed')
            ->and($presentation->durationLabel)->toBe('1:00:00')
            ->and($presentation->showProgressBar)->toBeFalse();
    });

    it('maps status colors', function (): void {
        expect(MediaItemPresenter::statusColor(MediaItemStatus::Completed->value))
            ->toContain('emerald')
            ->and(MediaItemPresenter::statusColor(MediaItemStatus::Filtered->value))
            ->toContain('slate');
    });
});
