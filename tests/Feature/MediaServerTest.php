<?php

declare(strict_types=1);

use App\Commands\DownloadMediaCommand;
use App\Commands\NotifyMediaServerCommand;
use App\Enums\MediaItemStatus;
use App\Enums\MediaServerLibraryType;
use App\Enums\MediaServerType;
use App\Enums\MetadataMode;
use App\Events\MediaItemIndexed;
use App\Models\GlobalSetting;
use App\Models\MediaItem;
use App\Models\MediaServer;
use App\Models\MediaServerLibrary;
use App\Repositories\SettingsRepository;
use App\Services\Core\ModelId;
use App\Services\MediaServer\JellyfinClient;
use App\Services\MediaServer\MediaItemCompletionService;
use App\Services\MediaServer\MediaMetadataWriter;
use App\Services\MediaServer\MediaServerClientFactory;
use App\Services\MediaServer\MediaServerNotificationService;
use App\Services\MediaServer\MediaServerOutputTemplateResolver;
use App\Services\MediaServer\MediaServerPathMapper;
use App\Services\MediaServer\MediaServerRefreshDebouncer;
use App\Services\MediaServer\MediaServerSyncService;
use App\Services\MediaServer\PlexClient;
use App\Services\MediaServer\SeasonEpisodeResolver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tempest\CommandBus\CommandRepository;
use Tempest\DateTime\DateTime;
use Tempest\EventBus\EventBus;
use Tests\Support\Fixtures;

describe('SeasonEpisodeResolver', function (): void {
    it('ranks episodes by publishedAt ascending with id tie-breakers', function (): void {
        $source = Fixtures::source(['title' => 'Ranked Show']);
        $second = Fixtures::mediaItem($source, [
            'ytId' => 'ep-second',
            'title' => 'Second',
            'publishedAt' => DateTime::parse('2024-02-01'),
        ]);
        $first = Fixtures::mediaItem($source, [
            'ytId' => 'ep-first',
            'title' => 'First',
            'publishedAt' => DateTime::parse('2024-01-01'),
        ]);
        $third = Fixtures::mediaItem($source, [
            'ytId' => 'ep-third',
            'title' => 'Third',
            'publishedAt' => DateTime::parse('2024-03-01'),
        ]);

        $second->refresh();
        $first->refresh();
        $third->refresh();

        $resolver = $this->container->get(SeasonEpisodeResolver::class);

        expect($resolver->resolve($source, $first))->toBe(1)
            ->and($resolver->resolve($source, $second))->toBe(2)
            ->and($resolver->resolve($source, $third))->toBe(3);

        $first->refresh();
        $second->refresh();

        expect($first->seasonEpisode)->toBe(1)
            ->and($second->seasonEpisode)->toBe(2);
    });

    it('returns a cached season episode without recomputing', function (): void {
        $source = Fixtures::source();
        $item = Fixtures::mediaItem($source, ['seasonEpisode' => 7]);

        $resolver = $this->container->get(SeasonEpisodeResolver::class);

        expect($resolver->resolve($source, $item))->toBe(7);
    });
});

describe('MediaServerOutputTemplateResolver', function (): void {
    it('uses a TV show template with season folder and episode number', function (): void {
        ['source' => $source] = Fixtures::sourceWithMediaServerLibrary([], ['libraryType' => MediaServerLibraryType::Tv]);
        $item = Fixtures::mediaItem($source, [
            'ytId' => 'tv001',
            'title' => 'Pilot Episode',
            'publishedAt' => DateTime::parse('2024-06-01'),
        ]);

        $resolver = $this->container->get(MediaServerOutputTemplateResolver::class);
        $template = $resolver->resolveVideoTemplate($source, $item);

        expect($template)
            ->toContain('/tmp/tubecast-test/video/Critical Role/Season 01/')
            ->toContain('s01e01')
            ->toContain('Pilot Episode')
            ->toContain('%(id)s');
    });

    it('uses a movie template with year folder when publishedAt is set', function (): void {
        ['source' => $source] = Fixtures::sourceWithMediaServerLibrary([], ['libraryType' => MediaServerLibraryType::Movie]);
        $item = Fixtures::mediaItem($source, [
            'ytId' => 'mv001',
            'title' => 'Standalone Film',
            'publishedAt' => DateTime::parse('2022-08-15'),
        ]);

        $resolver = $this->container->get(MediaServerOutputTemplateResolver::class);
        $template = $resolver->resolveVideoTemplate($source, $item);

        expect($template)->toBe('/tmp/tubecast-test/video/Standalone Film (2022)/Standalone Film (2022).%(ext)s');
    });

    it('returns the source output template when configured', function (): void {
        $source = Fixtures::source(['outputTemplate' => '%(title)s.%(ext)s']);
        $item = Fixtures::mediaItem($source);

        $resolver = $this->container->get(MediaServerOutputTemplateResolver::class);

        expect($resolver->resolveVideoTemplate($source, $item))->toBe('%(title)s.%(ext)s');
    });
});

describe('MediaMetadataWriter', function (): void {
    it('writes tvshow and episode NFO sidecars for TV libraries', function (): void {
        $showDir = '/tmp/tubecast-test/video/Critical Role/Season 01';
        if (! is_dir($showDir)) {
            mkdir($showDir, 0755, true);
        }

        $videoPath = $showDir . '/Critical Role - s01e01 - Episode One [ep001].mp4';
        file_put_contents($videoPath, 'fake video');

        ['source' => $source] = Fixtures::sourceWithMediaServerLibrary([
            'title' => 'Critical Role',
            'tmdbSeriesId' => 12345,
            'tvdbSeriesId' => 67890,
        ]);
        $item = Fixtures::mediaItem($source, [
            'ytId' => 'ep001',
            'title' => 'Episode One',
            'description' => 'The adventure begins',
            'publishedAt' => DateTime::parse('2024-01-10'),
            'filePath' => $videoPath,
        ]);

        $this->container->get(MediaMetadataWriter::class)->writeForCompletedItem($source, $item);

        $showNfo = $showDir . '/tvshow.nfo';
        $episodeNfo = preg_replace('/\.[^.]+$/', '', $videoPath) . '.nfo';

        expect(is_file($showNfo))->toBeTrue()
            ->and(file_get_contents($showNfo))->toContain('<title>Critical Role</title>')
            ->and(is_file($episodeNfo))->toBeTrue()
            ->and(file_get_contents($episodeNfo))->toContain('<episode>1</episode>')
            ->and(file_get_contents($episodeNfo))->toContain('<uniqueid type="tmdb">12345</uniqueid>')
            ->and(file_get_contents($episodeNfo))->toContain('<uniqueid type="tvdb">67890</uniqueid>');

        @unlink($videoPath);
        @unlink($episodeNfo);
        @unlink($showNfo);
    });

    it('skips NFO generation for non-TV libraries', function (): void {
        ['source' => $source] = Fixtures::sourceWithMediaServerLibrary([], ['libraryType' => MediaServerLibraryType::Movie]);
        $videoPath = '/tmp/tubecast-test/video/movie-only.mp4';
        file_put_contents($videoPath, 'fake video');

        $item = Fixtures::mediaItem($source, [
            'filePath' => $videoPath,
        ]);

        $this->container->get(MediaMetadataWriter::class)->writeForCompletedItem($source, $item);

        expect(is_file(preg_replace('/\.[^.]+$/', '', $videoPath) . '.nfo'))->toBeFalse();

        @unlink($videoPath);
    });
});

describe('MediaItemCompletionService', function (): void {
    it('marks an item completed once and dispatches follow-up commands', function (): void {
        ['source' => $source] = Fixtures::sourceWithMediaServerLibrary();
        $item = Fixtures::mediaItem($source, ['status' => MediaItemStatus::Downloading]);

        $completion = $this->container->get(MediaItemCompletionService::class);

        expect($completion->markCompleted($source, $item))->toBeTrue();

        $item->refresh();

        expect($item->status)->toBe(MediaItemStatus::Completed)
            ->and($completion->markCompleted($source, $item))->toBeFalse();

        $pending = $this->container->get(CommandRepository::class)->getPendingCommands();
        $itemId = ModelId::int($item->id);

        expect(array_filter(
            $pending,
            fn ($command): bool => $command instanceof NotifyMediaServerCommand
                && $command->mediaItemId === $itemId,
        ))->not->toBeEmpty();
    });
});

describe('MediaServerRefreshDebouncer', function (): void {
    it('suppresses duplicate refreshes within the debounce window', function (): void {
        $settings = $this->container->get(SettingsRepository::class);
        $debouncer = new MediaServerRefreshDebouncer($settings);

        expect($debouncer->shouldSkip(5, '/mnt/nas/show/episode.mp4'))->toBeFalse();

        $debouncer->record(5, '/mnt/nas/show/episode.mp4');

        expect($debouncer->shouldSkip(5, '/mnt/nas/show/episode.mp4'))->toBeTrue();

        $settings->set(
            'mediaServerRefresh_5_' . sha1('/mnt/nas/show/episode.mp4'),
            (string) (time() - 60),
        );

        expect($debouncer->shouldSkip(5, '/mnt/nas/show/episode.mp4'))->toBeFalse();
    });
});

describe('MediaServerSyncService', function (): void {
    it('upserts remote libraries and disables stale rows', function (): void {
        $server = Fixtures::mediaServer(['type' => MediaServerType::Plex]);
        $stale = Fixtures::mediaServerLibrary($server, [
            'externalId' => 'old-lib',
            'name' => 'Removed Library',
        ]);

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'MediaContainer' => [
                    'Directory' => [[
                        'key' => '1',
                        'title' => 'Synced TV',
                        'type' => 'show',
                        'Location' => [['path' => '/remote/tv']],
                    ]],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $plex = new PlexClient(new Client(['handler' => HandlerStack::create($mock)]));
        $sync = new MediaServerSyncService(new MediaServerClientFactory($plex, new JellyfinClient()));

        $count = $sync->sync($server);

        expect($count)->toBe(1);

        $server->refresh();
        $synced = MediaServerLibrary::select()
            ->where('mediaServerId = ? AND externalId = ?', ModelId::int($server->id), '1')
            ->first();
        $stale->refresh();

        expect($synced)->not->toBeNull()
            ->and($synced->name)->toBe('Synced TV')
            ->and($synced->enabled)->toBeTrue()
            ->and($synced->remoteRoot)->toBe('/remote/tv')
            ->and($stale->enabled)->toBeFalse()
            ->and($server->lastSyncError)->toBeNull()
            ->and($server->lastSyncedAt)->not->toBeNull();
    });
});

describe('MediaServerNotificationService', function (): void {
    it('refreshes the mapped Plex path after a completed video download', function (): void {
        $mock = new MockHandler([
            new Response(200, [], '{}'),
        ]);

        $plex = new PlexClient(new Client(['handler' => HandlerStack::create($mock)]));
        $notification = new MediaServerNotificationService(
            new MediaServerClientFactory($plex, new JellyfinClient()),
            new MediaServerPathMapper(),
            new MediaServerRefreshDebouncer($this->container->get(SettingsRepository::class)),
        );

        ['server' => $server, 'library' => $library, 'source' => $source] = Fixtures::sourceWithMediaServerLibrary(
            ['saveVideo' => true],
            ['remoteRoot' => '/mnt/nas/youtube'],
        );

        $filePath = rtrim($server->tubecastVideoRoot, '/') . '/Show/Season 01/episode.mp4';
        $item = Fixtures::mediaItem($source, [
            'status' => MediaItemStatus::Completed,
            'filePath' => $filePath,
        ]);

        $notification->notifyForCompletedItem(ModelId::int($item->id), ModelId::int($source->id));

        expect($mock->count())->toBe(0);
    });

    it('falls back to a full library refresh when path refresh fails', function (): void {
        $mock = new MockHandler([
            new Response(500, [], 'error'),
            new Response(200, [], '{}'),
        ]);

        $plex = new PlexClient(new Client(['handler' => HandlerStack::create($mock)]));
        $notification = new MediaServerNotificationService(
            new MediaServerClientFactory($plex, new JellyfinClient()),
            new MediaServerPathMapper(),
            new MediaServerRefreshDebouncer($this->container->get(SettingsRepository::class)),
        );

        ['server' => $server, 'source' => $source] = Fixtures::sourceWithMediaServerLibrary(
            ['saveVideo' => true],
            ['remoteRoot' => '/mnt/nas/youtube'],
        );

        $filePath = rtrim($server->tubecastVideoRoot, '/') . '/Show/episode.mp4';
        $item = Fixtures::mediaItem($source, [
            'status' => MediaItemStatus::Completed,
            'filePath' => $filePath,
        ]);

        $notification->notifyForCompletedItem(ModelId::int($item->id), ModelId::int($source->id));

        expect($mock->count())->toBe(0);
    });

    it('does nothing when media server notification is disabled on the source', function (): void {
        $mock = new MockHandler([
            new Response(200, [], '{}'),
        ]);

        $plex = new PlexClient(new Client(['handler' => HandlerStack::create($mock)]));
        $notification = new MediaServerNotificationService(
            new MediaServerClientFactory($plex, new JellyfinClient()),
            new MediaServerPathMapper(),
            new MediaServerRefreshDebouncer($this->container->get(SettingsRepository::class)),
        );

        $source = Fixtures::source(['notifyMediaServer' => false, 'saveVideo' => true]);
        $item = Fixtures::mediaItem($source, [
            'status' => MediaItemStatus::Completed,
            'filePath' => '/tmp/tubecast-test/video/episode.mp4',
        ]);

        $notification->notifyForCompletedItem(ModelId::int($item->id), ModelId::int($source->id));

        expect($mock->count())->toBe(1);
    });
});

describe('MediaItemIndexed event', function (): void {
    it('queues auto-download commands for newly indexed matching episodes', function (): void {
        $source = Fixtures::source(['saveVideo' => true]);
        $item = Fixtures::mediaItem($source, ['status' => MediaItemStatus::Indexed]);

        $this->container->get(EventBus::class)->dispatch(new MediaItemIndexed(
            mediaItemId: ModelId::int($item->id),
            sourceId: ModelId::int($source->id),
            newlyCreated: true,
        ));

        $pending = $this->container->get(CommandRepository::class)->getPendingCommands();
        $itemId = ModelId::int($item->id);

        expect(array_filter(
            $pending,
            fn ($command): bool => $command instanceof DownloadMediaCommand && $command->mediaItemId === $itemId,
        ))->not->toBeEmpty();
    });

    it('does not queue downloads for re-indexed episodes', function (): void {
        $source = Fixtures::source(['saveVideo' => true]);
        $item = Fixtures::mediaItem($source, ['status' => MediaItemStatus::Indexed]);

        $this->container->get(EventBus::class)->dispatch(new MediaItemIndexed(
            mediaItemId: ModelId::int($item->id),
            sourceId: ModelId::int($source->id),
            newlyCreated: false,
        ));

        $pending = $this->container->get(CommandRepository::class)->getPendingCommands();
        $itemId = ModelId::int($item->id);

        expect(array_filter(
            $pending,
            fn ($command): bool => $command instanceof DownloadMediaCommand && $command->mediaItemId === $itemId,
        ))->toBeEmpty();
    });
});

describe('Media server settings HTTP', function (): void {
    it('creates, updates, and deletes media servers', function (): void {
        $this->authedPost('/settings/media-servers', [
            'name' => 'Home Plex',
            'type' => 'plex',
            'baseUrl' => 'http://plex.test:32400',
            'apiToken' => 'plex-secret',
            'tubecastVideoRoot' => '/tmp/tubecast-test/video',
            'tubecastAudioRoot' => '/tmp/tubecast-test/audio',
            'enabled' => '1',
        ])->assertRedirect('/settings#media-servers');

        $server = MediaServer::select()->where('name = ?', 'Home Plex')->first();

        expect($server)->not->toBeNull()
            ->and($server->type)->toBe(MediaServerType::Plex)
            ->and($server->baseUrl)->toBe('http://plex.test:32400');

        $serverId = ModelId::int($server->id);

        $this->authedPost('/settings/media-servers/' . $serverId, [
            'name' => 'Renamed Plex',
            'type' => 'plex',
            'baseUrl' => 'http://plex.test:32401',
            'apiToken' => 'plex-secret',
            'tubecastVideoRoot' => '/tmp/tubecast-test/video',
            'tubecastAudioRoot' => '/tmp/tubecast-test/audio',
            'enabled' => '1',
        ])->assertRedirect('/settings#media-servers');

        $server->refresh();
        expect($server->name)->toBe('Renamed Plex')
            ->and($server->baseUrl)->toBe('http://plex.test:32401');

        $this->authedPost('/settings/media-servers/' . $serverId . '/delete')
            ->assertRedirect('/settings#media-servers');

        expect(MediaServer::findById($serverId))->toBeNull();
    });

    it('records connection errors from the test action', function (): void {
        $server = Fixtures::mediaServer([
            'baseUrl' => 'http://127.0.0.1:1',
            'apiToken' => 'bad-token',
        ]);
        $serverId = ModelId::int($server->id);

        $this->authedPost('/settings/media-servers/' . $serverId . '/test')
            ->assertRedirect('/settings#media-servers');

        $server->refresh();

        expect($server->lastSyncError)->not->toBeNull();
    });

    it('updates metadata provider API keys', function (): void {
        $this->authedPost('/settings/metadata-providers', [
            'tmdbApiKey' => 'tmdb-test-key',
            'tvdbApiKey' => 'tvdb-test-key',
        ])->assertRedirect('/settings#metadata-providers');

        $settings = $this->container->get(SettingsRepository::class);

        expect($settings->get('tmdbApiKey'))->toBe('tmdb-test-key')
            ->and($settings->get('tvdbApiKey'))->toBe('tvdb-test-key');
    });
});

describe('Source media server settings HTTP', function (): void {
    it('persists notify and library settings from the source form', function (): void {
        $server = Fixtures::mediaServer();
        $library = Fixtures::mediaServerLibrary($server);
        $source = Fixtures::source(['notifyMediaServer' => false]);
        $sourceId = ModelId::int($source->id);
        $libraryId = ModelId::int($library->id);

        $this->authedPost('/sources/' . $sourceId . '/settings', [
            'notifyMediaServer' => '1',
            'mediaServerLibraryId' => (string) $libraryId,
            'metadataMode' => MetadataMode::Tmdb->value,
            'tmdbSeriesId' => '999',
            'tvdbSeriesId' => '888',
        ])->assertRedirect('/sources/' . $sourceId);

        $source->refresh();

        expect($source->notifyMediaServer)->toBeTrue()
            ->and($source->mediaServerLibraryId)->toBe($libraryId)
            ->and($source->metadataMode)->toBe(MetadataMode::Tmdb)
            ->and($source->tmdbSeriesId)->toBe(999)
            ->and($source->tvdbSeriesId)->toBeNull();
    });

    it('shows the media server section on the edit form', function (): void {
        ['source' => $source] = Fixtures::sourceWithMediaServerLibrary();
        $sourceId = ModelId::int($source->id);

        $this->authedGet('/sources/' . $sourceId . '/edit')
            ->assertOk()
            ->assertSee('Media server')
            ->assertSee('name="notifyMediaServer"', false)
            ->assertSee('name="mediaServerLibraryId"', false);
    });

    it('links to settings when no libraries exist', function (): void {
        $source = Fixtures::source();
        $sourceId = ModelId::int($source->id);

        $this->authedGet('/sources/' . $sourceId . '/edit')
            ->assertOk()
            ->assertSee('Configure media servers in Settings');
    });
});
