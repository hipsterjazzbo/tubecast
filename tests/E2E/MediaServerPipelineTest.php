<?php

declare(strict_types=1);

use App\Commands\DownloadMediaCommand;
use App\Commands\Handlers\DownloadMediaCommandHandler;
use App\Commands\Handlers\NotifyMediaServerCommandHandler;
use App\Commands\NotifyMediaServerCommand;
use App\Enums\MediaItemStatus;
use App\Enums\MediaServerLibraryType;
use App\Enums\MediaServerType;
use App\Enums\MetadataMode;
use App\Models\MediaServer;
use App\Models\MediaServerLibrary;
use App\Models\Source;
use App\Services\Core\ModelId;
use App\Services\MediaServer\MediaItemCompletionService;
use App\Services\MediaServer\MediaServerPathMapper;
use Tempest\CommandBus\CommandRepository;
use Tempest\DateTime\DateTime;
use Tests\E2E\Support\MediaServerE2e;
use Tests\E2E\Support\MockMediaServerHttp;
use Tests\Support\Fixtures;

describe('Media server end-to-end pipeline', function (): void {
    beforeEach(function (): void {
        $this->mockServer = new MockMediaServerHttp();
        $this->mockServer->start();
        $this->loginAsAdmin();
    });

    afterEach(function (): void {
        $this->mockServer?->stop();
    });

    it('registers a Plex server via HTTP, syncs libraries, and tests the connection', function (): void {
        $baseUrl = $this->mockServer->baseUrl();

        $this->authedPost('/settings/media-servers', [
            'name' => 'E2E Plex',
            'type' => 'plex',
            'baseUrl' => $baseUrl,
            'apiToken' => 'e2e-plex-token',
            'tubecastVideoRoot' => '/tmp/tubecast-test/video',
            'tubecastAudioRoot' => '/tmp/tubecast-test/audio',
            'enabled' => '1',
        ])->assertRedirect('/settings#media-servers');

        $server = MediaServer::select()->where('name = ?', 'E2E Plex')->first();

        expect($server)->not->toBeNull();

        $serverId = ModelId::int($server->id);

        $this->authedPost('/settings/media-servers/' . $serverId . '/test')
            ->assertRedirect('/settings#media-servers');

        $server->refresh();
        expect($server->lastSyncError)->toBeNull();

        $this->mockServer->assertRequestReceived(
            fn (array $request): bool => $request['method'] === 'GET'
                && $request['path'] === '/library/sections',
            'Plex test connection should fetch library sections.',
        );

        $this->mockServer->clearRequests();

        $this->authedPost('/settings/media-servers/' . $serverId . '/sync')
            ->assertRedirect('/settings#media-servers');

        $server->refresh();
        $library = MediaServerLibrary::select()
            ->where('mediaServerId = ? AND externalId = ?', $serverId, '42')
            ->first();

        expect($server->lastSyncError)->toBeNull()
            ->and($server->lastSyncedAt)->not->toBeNull()
            ->and($library)->not->toBeNull()
            ->and($library->name)->toBe('E2E TV Library')
            ->and($library->libraryType)->toBe(MediaServerLibraryType::Tv)
            ->and($library->remoteRoot)->toBe('/mock/nas/e2e-tv')
            ->and($library->enabled)->toBeTrue();
    });

    it('runs completion through async notify and refreshes Plex with the mapped remote path', function (): void {
        $baseUrl = $this->mockServer->baseUrl();
        $server = Fixtures::mediaServer([
            'name' => 'Pipeline Plex',
            'type' => MediaServerType::Plex,
            'baseUrl' => $baseUrl,
            'apiToken' => 'pipeline-token',
        ]);
        $library = Fixtures::mediaServerLibrary($server, [
            'externalId' => '42',
            'remoteRoot' => '/mock/nas/e2e-tv',
            'libraryType' => MediaServerLibraryType::Tv,
        ]);

        $source = Fixtures::source([
            'title' => 'E2E Campaign',
            'saveVideo' => true,
            'notifyMediaServer' => true,
            'mediaServerLibraryId' => ModelId::int($library->id),
            'metadataMode' => MetadataMode::Local,
        ]);

        $videoPath = MediaServerE2e::placeTvEpisodeFile('E2E Campaign', 1, 'Pilot', 'e2e-pilot-001');
        $item = Fixtures::mediaItem($source, [
            'ytId' => 'e2e-pilot-001',
            'title' => 'Pilot',
            'publishedAt' => DateTime::parse('2024-01-01'),
            'status' => MediaItemStatus::Downloading,
            'filePath' => $videoPath,
        ]);

        $completion = $this->container->get(MediaItemCompletionService::class);

        expect($completion->markCompleted($source, $item))->toBeTrue();

        $item->refresh();
        expect($item->status)->toBe(MediaItemStatus::Completed)
            ->and(is_file(dirname($videoPath) . '/tvshow.nfo'))->toBeTrue()
            ->and(is_file(preg_replace('/\.[^.]+$/', '', $videoPath) . '.nfo'))->toBeTrue();

        $pending = $this->container->get(CommandRepository::class)->getPendingCommands();
        $itemId = ModelId::int($item->id);

        expect(array_filter(
            $pending,
            fn ($command): bool => $command instanceof NotifyMediaServerCommand
                && $command->mediaItemId === $itemId,
        ))->not->toBeEmpty();

        $this->mockServer->clearRequests();
        MediaServerE2e::runPendingNotifyCommands(
            $this->container->get(CommandRepository::class),
            $this->container->get(NotifyMediaServerCommandHandler::class),
        );

        $mapper = new MediaServerPathMapper();
        $expectedRemote = $mapper->mapForSource($source, $server, $library, $videoPath);

        expect($expectedRemote)->not->toBeNull();

        $this->mockServer->assertRequestReceived(
            fn (array $request): bool => $request['method'] === 'GET'
                && str_contains($request['path'], '/library/sections/42/refresh')
                && ($request['query']['path'] ?? null) === $expectedRemote,
            'Plex should receive a path refresh for the mapped remote file.',
        );
    });

    it('finalizes an on-disk download and notifies Plex without yt-dlp', function (): void {
        $baseUrl = $this->mockServer->baseUrl();
        $server = Fixtures::mediaServer([
            'name' => 'Download Plex',
            'type' => MediaServerType::Plex,
            'baseUrl' => $baseUrl,
        ]);
        $library = Fixtures::mediaServerLibrary($server, [
            'externalId' => '42',
            'remoteRoot' => '/mock/nas/e2e-tv',
        ]);

        $source = Fixtures::source([
            'title' => 'Download Show',
            'saveVideo' => true,
            'notifyMediaServer' => true,
            'mediaServerLibraryId' => ModelId::int($library->id),
        ]);

        $ytId = 'e2e-download-001';
        MediaServerE2e::placeTvEpisodeFile('Download Show', 1, 'Recovered Episode', $ytId);

        $item = Fixtures::mediaItem($source, [
            'ytId' => $ytId,
            'title' => 'Recovered Episode',
            'publishedAt' => DateTime::parse('2024-02-01'),
            'status' => MediaItemStatus::Indexed,
        ]);

        $this->container->get(DownloadMediaCommandHandler::class)
            ->__invoke(new DownloadMediaCommand(ModelId::int($item->id)));

        $item->refresh();
        expect($item->status)->toBe(MediaItemStatus::Completed);

        $this->mockServer->clearRequests();
        MediaServerE2e::runPendingNotifyCommands(
            $this->container->get(CommandRepository::class),
            $this->container->get(NotifyMediaServerCommandHandler::class),
        );

        $this->mockServer->assertRequestReceived(
            fn (array $request): bool => $request['method'] === 'GET'
                && str_contains($request['path'], '/library/sections/42/refresh')
                && str_contains((string) ($request['query']['path'] ?? ''), '/mock/nas/e2e-tv/Download Show/Season 01/'),
            'Download recovery should notify Plex after finalizing the on-disk file.',
        );
    });

    it('syncs Jellyfin libraries and refreshes after completion', function (): void {
        $baseUrl = $this->mockServer->baseUrl();

        $this->authedPost('/settings/media-servers', [
            'name' => 'E2E Jellyfin',
            'type' => 'jellyfin',
            'baseUrl' => $baseUrl,
            'apiToken' => 'e2e-jellyfin-token',
            'tubecastVideoRoot' => '/tmp/tubecast-test/video',
            'tubecastAudioRoot' => '/tmp/tubecast-test/audio',
            'enabled' => '1',
        ])->assertRedirect('/settings#media-servers');

        $server = MediaServer::select()->where('name = ?', 'E2E Jellyfin')->first();
        expect($server)->not->toBeNull()
            ->and($server->type)->toBe(MediaServerType::Jellyfin);

        $serverId = ModelId::int($server->id);

        $this->authedPost('/settings/media-servers/' . $serverId . '/sync')
            ->assertRedirect('/settings#media-servers');

        $library = MediaServerLibrary::select()
            ->where('mediaServerId = ? AND externalId = ?', $serverId, 'jf-show-1')
            ->first();

        expect($library)->not->toBeNull()
            ->and($library->remoteRoot)->toBe('/mock/jellyfin/e2e-tv');

        $libraryId = ModelId::int($library->id);
        $sourceId = ModelId::int(Fixtures::source([
            'title' => 'Jellyfin Show',
            'saveVideo' => true,
        ])->id);

        $this->authedPost('/sources/' . $sourceId . '/settings', [
            'notifyMediaServer' => '1',
            'mediaServerLibraryId' => (string) $libraryId,
            'metadataMode' => MetadataMode::Local->value,
        ])->assertRedirect('/sources/' . $sourceId);

        $source = Source::findById($sourceId);
        expect($source?->notifyMediaServer)->toBeTrue()
            ->and($source?->mediaServerLibraryId)->toBe($libraryId);

        $videoPath = MediaServerE2e::placeTvEpisodeFile('Jellyfin Show', 1, 'Premiere', 'e2e-jf-001');
        $item = Fixtures::mediaItem($source, [
            'ytId' => 'e2e-jf-001',
            'title' => 'Premiere',
            'publishedAt' => DateTime::parse('2024-03-01'),
            'status' => MediaItemStatus::Downloading,
            'filePath' => $videoPath,
        ]);

        $this->container->get(MediaItemCompletionService::class)->markCompleted($source, $item);

        $this->mockServer->clearRequests();
        MediaServerE2e::runPendingNotifyCommands(
            $this->container->get(CommandRepository::class),
            $this->container->get(NotifyMediaServerCommandHandler::class),
        );

        $this->mockServer->assertRequestReceived(
            fn (array $request): bool => $request['method'] === 'POST'
                && $request['path'] === '/Library/Media/Updated',
            'Jellyfin should receive a library refresh after completion.',
        );
    });
})->skip(
    fn (): bool => getenv('TUBECAST_E2E') !== '1',
    'Set TUBECAST_E2E=1 to run media server end-to-end tests.',
);
