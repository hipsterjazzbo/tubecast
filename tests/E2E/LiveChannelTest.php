<?php

declare(strict_types=1);

use App\Services\YouTubeChannelResolver;
use App\Services\YouTubeRssService;

describe('Live channel resolution', function (): void {
    it('resolves Critical Role from a /c/ URL', function (): void {
        $resolver = $this->container->get(YouTubeChannelResolver::class);

        $channelId = $resolver->resolve('https://www.youtube.com/c/criticalrole');

        expect($channelId)->toBe('UCpXBGqwsBkpvcYjsJBQ7LEQ');
    });

    it('resolves Oculus Imperia from its vanity URL', function (): void {
        $resolver = $this->container->get(YouTubeChannelResolver::class);

        $channelId = $resolver->resolve('https://www.youtube.com/oculusimperia');

        expect($channelId)->not->toBeNull()
            ->and(strlen($channelId))->toBeGreaterThan(10);
    });
});

describe('Live RSS indexing', function (): void {
    it('fetches recent entries from the Critical Role channel feed', function (): void {
        $rss = new YouTubeRssService();
        $url = 'https://www.youtube.com/feeds/videos.xml?channel_id=UCpXBGqwsBkpvcYjsJBQ7LEQ';

        $entries = $rss->fetchEntries($url);

        expect($entries)->not->toBeEmpty();

        $first = $entries[0];
        expect($first->videoId)->not->toBe('')
            ->and($first->title)->not->toBe('')
            ->and($first->url)->toContain('watch?v=');
    });

    it('reads the Critical Role feed title', function (): void {
        $rss = new YouTubeRssService();
        $url = 'https://www.youtube.com/feeds/videos.xml?channel_id=UCpXBGqwsBkpvcYjsJBQ7LEQ';

        $title = $rss->fetchFeedTitle($url);

        expect($title)->not->toBeNull()
            ->and($title)->toContain('Critical Role');
    });
});

describe('Live yt-dlp metadata', function (): void {
    it('extracts Critical Role channel metadata without downloading media', function (): void {
        $ytDlp = new Ytdlphp\YtDlp('yt-dlp');

        $info = $ytDlp->extractInfo('https://www.youtube.com/@CriticalRole', [
            'flatPlaylist' => true,
            'playlistEnd' => 1,
        ]);

        expect($info->raw['channel_id'] ?? $info->raw['id'] ?? null)->toBe('UCpXBGqwsBkpvcYjsJBQ7LEQ');
    })->group('slow');
});
