<?php

declare(strict_types=1);

use App\Commands\DownloadMediaCommand;
use App\Commands\FastIndexSourceCommand;
use App\Commands\FullIndexSourceCommand;
use App\Enums\DiscoveredVia;
use App\Enums\MediaItemStatus;
use App\Models\Feed;
use App\Models\GlobalSetting;
use App\Models\MediaItem;
use App\Models\Source;
use App\Services\Download\DownloadRecoveryService;
use App\Services\Download\OutputPathBuilder;
use App\Services\YouTube\YouTubeDataApiService;
use App\Services\Core\ModelId;
use App\Config\TubecastConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\Support\Fixtures;

describe('Critical Role video workflow', function (): void {
    it('adds a channel source via HTTP and queues a full index', function (): void {
        $response = $this->authedPost('/sources', [
            'url' => 'https://www.youtube.com/channel/UCpXBGqwsBkpvcYjsJBQ7LEQ',
            'title' => 'Critical Role',
            'saveVideo' => '1',
            'downloadMode' => 'auto',
        ])->assertRedirect();

        $source = Source::select()->where('title = ?', 'Critical Role')->first();

        expect($source)->not->toBeNull()
            ->and($source->youtubeChannelId)->toBe('UCpXBGqwsBkpvcYjsJBQ7LEQ')
            ->and($source->saveVideo)->toBeTrue()
            ->and($source->saveAudio)->toBeFalse();

        $feed = Feed::select()->where('sourceId = ?', ModelId::int($source->id))->first();
        expect($feed)->not->toBeNull();

        $pending = $this->container->get(\Tempest\CommandBus\CommandRepository::class)->getPendingCommands();
        $sourceId = ModelId::int($source->id);

        expect(array_filter(
            $pending,
            fn ($command): bool => $command instanceof FullIndexSourceCommand && $command->sourceId === $sourceId,
        ))->not->toBeEmpty();

        $response->assertRedirect('/sources/' . $sourceId);
    });

    it('indexes episodes through the YouTube API handler', function (): void {
        GlobalSetting::create(settingKey: 'youtubeApiKey', value: 'test-key');

        $source = Fixtures::criticalRoleSource();
        $sourceId = ModelId::int($source->id);

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'items' => [[
                    'id' => 'UCpXBGqwsBkpvcYjsJBQ7LEQ',
                    'snippet' => ['title' => 'Critical Role'],
                    'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UULFpXBGqwsBkpvcYjsJBQ7LEQ']],
                    'statistics' => ['videoCount' => 1],
                ]],
            ], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode([
                'items' => [[
                    'id' => 'UCpXBGqwsBkpvcYjsJBQ7LEQ',
                    'snippet' => ['title' => 'Critical Role'],
                    'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UULFpXBGqwsBkpvcYjsJBQ7LEQ']],
                    'statistics' => ['videoCount' => 1],
                ]],
            ], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode([
                'items' => [[
                    'snippet' => ['resourceId' => ['videoId' => 'cr001']],
                    'contentDetails' => ['videoId' => 'cr001'],
                ]],
            ], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode([
                'items' => [[
                    'id' => 'cr001',
                    'snippet' => [
                        'title' => 'Campaign 3 Episode 1',
                        'description' => 'Long-form episode',
                        'publishedAt' => '2024-01-15T12:00:00Z',
                        'liveBroadcastContent' => 'none',
                    ],
                    'contentDetails' => ['duration' => 'PT4H0M0S'],
                ]],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $api = new YouTubeDataApiService(
            $this->container->get(\App\Repositories\SettingsRepository::class),
            $this->container->get(\App\Services\YouTube\YouTubeRssUrlBuilder::class),
            $this->container->get(\App\Services\YouTube\YouTubeChannelPageScraper::class),
            new Client(['handler' => HandlerStack::create($mock)]),
        );

        $handler = new \App\Commands\Handlers\FullIndexSourceCommandHandler(
            $this->container->get(\App\Services\Download\YtDlpService::class),
            $this->container->get(\App\Services\Core\ThrottleGuard::class),
            $this->container->get(\App\Services\Source\SourceMetadataService::class),
            $this->container->get(\App\Services\Source\EpisodeFilterService::class),
            $this->container->get(\App\Services\Source\MediaItemIndexingService::class),
            $api,
            $this->container->get(\Psr\Log\LoggerInterface::class),
        );

        $handler->__invoke(new FullIndexSourceCommand($sourceId));

        $item = MediaItem::select()
            ->where('sourceId = ? AND ytId = ?', $sourceId, 'cr001')
            ->first();

        expect($item)->not->toBeNull()
            ->and($item->title)->toBe('Campaign 3 Episode 1')
            ->and($item->status)->toBe(MediaItemStatus::Indexed)
            ->and($item->discoveredVia)->toBe(DiscoveredVia::YouTubeApi);

        $source->refresh();
        expect($source->indexExpectedTotal)->toBe(1)
            ->and($source->fullIndexProcessedCount)->toBe(1);
    });

    it('finalizes a completed video download from disk', function (): void {
        $root = sys_get_temp_dir() . '/tubecast-cr-' . uniqid('', true);
        $downloads = $root . '/downloads/Critical Role';
        mkdir($downloads, 0755, true);

        $ytId = 'cr-video';
        $videoPath = $downloads . '/' . $ytId . '.mp4';
        file_put_contents($videoPath, str_repeat('v', 8192));

        $config = new TubecastConfig(
            dataPath: $root,
            downloadsPath: $root . '/downloads',
            podcastPath: $root . '/podcast',
            ytDlpBinary: 'yt-dlp',
            workerConcurrency: 1,
            sleepInterval: 0,
            sleepRequests: 0,
            limitRate: null,
        );

        $recovery = new DownloadRecoveryService(
            new OutputPathBuilder($config),
            $this->container->get(\App\Services\Podcast\PodcastVariantService::class),
            $this->container->get(\Tempest\CommandBus\CommandBus::class),
            $this->container->get(\Tempest\Log\Logger::class),
        );

        $source = Fixtures::criticalRoleSource();
        $item = Fixtures::mediaItem($source, [
            'ytId' => $ytId,
            'status' => MediaItemStatus::Downloading,
            'filePath' => $videoPath,
        ]);

        expect($recovery->finalizeFromDisk($source, $item, null))->toBeTrue()
            ->and($item->status)->toBe(MediaItemStatus::Completed)
            ->and($item->filePath)->toBe($videoPath);

        unlink($videoPath);
        rmdir($downloads);
        rmdir($root . '/downloads');
        rmdir($root);
    });
});

describe('Oculus Imperia audio podcast workflow', function (): void {
    it('creates an audio-only source and RSS feed', function (): void {
        $source = Fixtures::oculusImperiaSource();
        Fixtures::feed($source);

        expect($source->saveVideo)->toBeFalse()
            ->and($source->saveAudio)->toBeTrue()
            ->and($source->url)->toBe('https://www.youtube.com/oculusimperia');

        $this->authedGet('/sources/' . ModelId::int($source->id))
            ->assertOk()
            ->assertSee('Oculus Imperia');
    });

    it('updates source settings to manual download mode', function (): void {
        $source = Fixtures::oculusImperiaSource(['saveAudio' => true]);
        $sourceId = ModelId::int($source->id);

        $this->authedPost('/sources/' . $sourceId . '/settings', [
            'title' => 'Oculus Imperia Podcast',
            'saveAudio' => '1',
            'downloadMode' => 'manual',
        ])->assertRedirect('/sources/' . $sourceId);

        $source->refresh();
        expect($source->title)->toBe('Oculus Imperia Podcast')
            ->and($source->filtersJson)->toContain('manual');
    });

    it('finalizes an audio-only podcast file from disk', function (): void {
        $source = Fixtures::oculusImperiaSource();
        $sourceId = ModelId::int($source->id);

        $root = sys_get_temp_dir() . '/tubecast-oi-' . uniqid('', true);
        $podcast = $root . '/podcast/' . $sourceId;
        mkdir($podcast, 0755, true);

        $ytId = 'oi001';
        file_put_contents($podcast . '/' . $ytId . '.m4a', str_repeat('a', 4096));

        $config = new TubecastConfig(
            dataPath: $root,
            downloadsPath: $root . '/downloads',
            podcastPath: $root . '/podcast',
            ytDlpBinary: 'yt-dlp',
            workerConcurrency: 1,
            sleepInterval: 0,
            sleepRequests: 0,
            limitRate: null,
        );

        $recovery = new DownloadRecoveryService(
            new OutputPathBuilder($config),
            $this->container->get(\App\Services\Podcast\PodcastVariantService::class),
            $this->container->get(\Tempest\CommandBus\CommandBus::class),
            $this->container->get(\Tempest\Log\Logger::class),
        );

        $item = Fixtures::mediaItem($source, [
            'ytId' => $ytId,
            'status' => MediaItemStatus::Downloading,
        ]);

        expect($recovery->finalizeFromDisk($source, $item, null))->toBeTrue()
            ->and($item->status)->toBe(MediaItemStatus::Completed)
            ->and($item->filePath)->toBeNull()
            ->and($item->podcastFilePath)->toContain($ytId . '.m4a');

        unlink($podcast . '/' . $ytId . '.m4a');
        rmdir($podcast);
        rmdir($root . '/podcast');
        rmdir($root);
    });

    it('queues a manual download from the episode page', function (): void {
        $source = Fixtures::oculusImperiaSource([
            'filtersJson' => json_encode(['downloadMode' => 'manual'], JSON_THROW_ON_ERROR),
        ]);
        $item = Fixtures::mediaItem($source, [
            'ytId' => 'oi-manual',
            'status' => MediaItemStatus::Indexed,
        ]);

        $sourceId = ModelId::int($source->id);
        $itemId = ModelId::int($item->id);

        $this->authedPost('/sources/' . $sourceId . '/episodes/' . $itemId . '/download')
            ->assertRedirect('/sources/' . $sourceId . '#episodes');

        $pending = $this->container->get(\Tempest\CommandBus\CommandRepository::class)->getPendingCommands();

        expect(array_filter(
            $pending,
            fn ($command): bool => $command instanceof DownloadMediaCommand && $command->mediaItemId === $itemId,
        ))->toHaveCount(1);
    });
});

describe('Source lifecycle', function (): void {
    it('queues a full re-index from the source page', function (): void {
        $source = Fixtures::criticalRoleSource();
        $sourceId = ModelId::int($source->id);

        $this->authedPost('/sources/' . $sourceId . '/index')
            ->assertRedirect('/sources/' . $sourceId);

        $pending = $this->container->get(\Tempest\CommandBus\CommandRepository::class)->getPendingCommands();

        expect(array_filter(
            $pending,
            fn ($command): bool => $command instanceof FullIndexSourceCommand && $command->sourceId === $sourceId,
        ))->not->toBeEmpty();
    });

    it('runs fast RSS indexing and discovers new episodes', function (): void {
        $source = Fixtures::criticalRoleSource();
        $sourceId = ModelId::int($source->id);

        $rssXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns:yt="http://www.youtube.com/xml/schemas/2015" xmlns="http://www.w3.org/2005/Atom">
  <title>Critical Role</title>
  <entry>
    <title>Fresh from RSS</title>
    <published>2024-06-01T12:00:00Z</published>
    <yt:videoId>rss-new</yt:videoId>
  </entry>
</feed>
XML;

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/atom+xml'], $rssXml),
        ]);

        $rss = new \App\Services\YouTube\YouTubeRssService(
            new Client(['handler' => HandlerStack::create($mock)]),
        );

        $handler = new \App\Commands\Handlers\FastIndexSourceCommandHandler(
            $rss,
            $this->container->get(\App\Services\Source\SourceMetadataService::class),
            $this->container->get(\App\Services\Source\EpisodeFilterService::class),
            $this->container->get(\Tempest\CommandBus\CommandBus::class),
            $this->container->get(\Psr\Log\LoggerInterface::class),
        );

        $handler->__invoke(new FastIndexSourceCommand($sourceId));

        $item = MediaItem::select()
            ->where('sourceId = ? AND ytId = ?', $sourceId, 'rss-new')
            ->first();

        expect($item)->not->toBeNull()
            ->and($item->title)->toBe('Fresh from RSS')
            ->and($item->discoveredVia)->toBe(DiscoveredVia::Rss);

        $source->refresh();
        expect($source->lastFastIndexedAt)->not->toBeNull();
    });
});
