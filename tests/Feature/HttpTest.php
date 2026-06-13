<?php

declare(strict_types=1);

use App\Enums\MediaItemStatus;
use App\Models\Feed;
use App\Models\Source;
use App\Services\Source\EpisodeFilterService;
use App\Services\Core\ModelId;
use Tests\Support\Fixtures;

describe('Episode filter evaluation', function (): void {
    it('marks matching episodes with filter result labels', function (): void {
        $source = Fixtures::source();
        $item = Fixtures::mediaItem($source, [
            'title' => 'Critical Role Campaign 3 Episode 1',
            'metadataJson' => json_encode([
                'id' => 'abc123',
                'title' => 'Critical Role Campaign 3 Episode 1',
                'duration' => 7200,
                'webpage_url' => 'https://www.youtube.com/watch?v=abc123',
                'live_status' => 'not_live',
            ], JSON_THROW_ON_ERROR),
        ]);

        $result = $this->container->get(EpisodeFilterService::class)->evaluateItem($source, $item);

        expect($result->matches)->toBeTrue()
            ->and($result->label())->toBe('Matches filter');
    });
});

describe('Dashboard', function (): void {
    it('renders the dashboard with stats', function (): void {
        $source = Fixtures::source(['title' => 'Critical Role']);
        Fixtures::mediaItem($source, ['status' => MediaItemStatus::Completed, 'title' => 'Episode One']);

        $this->authedGet('/')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('TubeCast')
            ->assertSee('Sources')
            ->assertSee('Episode One');
    });

    it('shows zero counts on an empty library', function (): void {
        $this->authedGet('/')
            ->assertOk()
            ->assertSee('Episodes');
    });
});

describe('Sources index', function (): void {
    it('lists sources with save mode labels', function (): void {
        Fixtures::source(['title' => 'Index Only Channel', 'saveVideo' => false, 'saveAudio' => false]);
        Fixtures::source(['title' => 'Video Saver', 'saveVideo' => true, 'saveAudio' => false]);

        $this->authedGet('/sources')
            ->assertOk()
            ->assertSee('Index Only Channel')
            ->assertSee('Video Saver')
            ->assertSee('Index only')
            ->assertSee('Saves Video');
    });
});

describe('Source detail', function (): void {
    it('shows episodes with filter badges when custom filters are configured', function (): void {
        $source = Fixtures::source([
            'title' => 'Filtered Channel',
            'filtersJson' => json_encode(['minDurationSeconds' => 600], JSON_THROW_ON_ERROR),
        ]);
        Fixtures::feed($source);
        Fixtures::mediaItem($source, [
            'ytId' => 'match1',
            'title' => 'Critical Role Campaign 3 Episode 1',
            'durationSeconds' => 7200,
            'metadataJson' => json_encode([
                'id' => 'match1',
                'title' => 'Critical Role Campaign 3 Episode 1',
                'duration' => 7200,
                'webpage_url' => 'https://www.youtube.com/watch?v=match1',
                'live_status' => 'not_live',
            ], JSON_THROW_ON_ERROR),
        ]);

        $sourceId = ModelId::int($source->id);

        $this->authedGet('/sources/' . $sourceId)
            ->assertOk()
            ->assertSee('Critical Role Campaign 3 Episode 1')
            ->assertSee('Matches filter');
    });

    it('hides match badges on default sources without custom filters', function (): void {
        $source = Fixtures::source(['title' => 'Plain Channel']);
        Fixtures::feed($source);
        Fixtures::mediaItem($source, [
            'ytId' => 'plain1',
            'title' => 'Regular Episode',
            'durationSeconds' => 7200,
            'metadataJson' => json_encode([
                'id' => 'plain1',
                'title' => 'Regular Episode',
                'duration' => 7200,
                'webpage_url' => 'https://www.youtube.com/watch?v=plain1',
                'live_status' => 'not_live',
            ], JSON_THROW_ON_ERROR),
        ]);

        $sourceId = ModelId::int($source->id);

        $this->authedGet('/sources/' . $sourceId)
            ->assertOk()
            ->assertSee('Regular Episode')
            ->assertNotSee('Matches filter');
    });

    it('returns episode partial for HTMX polling', function (): void {
        $source = Fixtures::source();
        Fixtures::mediaItem($source, ['title' => 'Partial Episode']);
        $sourceId = ModelId::int($source->id);

        $this->authedGet('/sources/' . $sourceId . '/episodes/partial')
            ->assertOk()
            ->assertSee('Partial Episode')
            ->assertSee('episodes-panel');
    });

    it('returns stats partial for HTMX polling', function (): void {
        $source = Fixtures::source();
        Fixtures::mediaItem($source);
        $sourceId = ModelId::int($source->id);

        $this->authedGet('/sources/' . $sourceId . '/stats/partial')
            ->assertOk()
            ->assertSee('source-stats')
            ->assertSee('Filter:');
    });
});

describe('Source settings', function (): void {
    it('updates save flags and filters', function (): void {
        $source = Fixtures::source(['saveVideo' => true, 'saveAudio' => false]);

        $sourceId = ModelId::int($source->id);

        $this->authedPost('/sources/' . $sourceId . '/settings', [
            'title' => 'Renamed Source',
            'saveVideo' => '1',
            'saveAudio' => '1',
            'includeShorts' => '1',
            'downloadMode' => 'manual',
            'minDurationMinutes' => '10',
            'titleRegex' => '/Campaign/i',
        ])->assertRedirect('/sources/' . $sourceId);

        $source->refresh();

        expect($source->title)->toBe('Renamed Source')
            ->and($source->saveVideo)->toBeTrue()
            ->and($source->saveAudio)->toBeTrue()
            ->and($source->includeShorts)->toBeTrue()
            ->and($source->filtersJson)->toContain('manual');
    });
});

describe('RSS feeds', function (): void {
    it('serves audio feed when token is valid', function (): void {
        $source = Fixtures::source(['saveAudio' => true]);
        $feed = Fixtures::feed($source, ['token' => 'secret-token']);

        $this->logoutSession();

        $this->http->get('/feeds/secret-token/audio.xml')
            ->assertOk()
            ->assertSee('<rss', false)
            ->assertSee($feed->title);
    });

    it('serves video feed when token is valid', function (): void {
        $source = Fixtures::source(['saveVideo' => true]);
        $feed = Fixtures::feed($source, ['token' => 'secret-token']);

        $this->logoutSession();

        $this->http->get('/feeds/secret-token/video.xml')
            ->assertOk()
            ->assertSee('<rss', false)
            ->assertSee($feed->title);
    });

    it('rejects feed requests with invalid token', function (): void {
        $source = Fixtures::source(['saveAudio' => true]);
        Fixtures::feed($source, ['token' => 'secret-token']);

        $this->logoutSession();

        $this->http->get('/feeds/wrong-token/audio.xml')
            ->assertNotFound();
    });

    it('rejects audio feed when source does not save audio', function (): void {
        $source = Fixtures::source(['saveAudio' => false]);
        Fixtures::feed($source, ['token' => 'secret-token']);

        $this->logoutSession();

        $this->http->get('/feeds/secret-token/audio.xml')
            ->assertNotFound();
    });
});

describe('Settings', function (): void {
    it('renders settings page', function (): void {
        $this->authedGet('/settings')
            ->assertOk()
            ->assertSee('Settings')
            ->assertSee('yt-dlp');
    });

    it('updates yt-dlp settings', function (): void {
        $this->authedPost('/settings/yt-dlp', [
            'ytDlpCookiesFile' => '/tmp/cookies.txt',
            'ytDlpProxy' => 'socks5://127.0.0.1:9050',
        ])->assertRedirect('/settings');
    });

    it('updates youtube api settings', function (): void {
        $this->authedPost('/settings/youtube-api', [
            'youtubeApiKey' => 'test-api-key',
        ])->assertRedirect('/settings');
    });

    it('renders media server configuration on the settings page', function (): void {
        Fixtures::mediaServer(['name' => 'Settings Plex']);

        $this->authedGet('/settings')
            ->assertOk()
            ->assertSee('Media servers')
            ->assertSee('Settings Plex')
            ->assertSee('Metadata providers');
    });
});
