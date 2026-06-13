<?php

declare(strict_types=1);

use App\Enums\MediaServerLibraryType;
use App\Enums\MediaServerType;
use App\Models\MediaServer;
use App\Services\MediaServer\JellyfinClient;
use App\Services\MediaServer\MediaServerException;
use App\Services\MediaServer\PlexClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

describe('PlexClient', function (): void {
    it('maps library sections from Plex JSON', function (): void {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'MediaContainer' => [
                    'Directory' => [[
                        'key' => '2',
                        'title' => 'TV Shows',
                        'type' => 'show',
                        'Location' => [['path' => '/mnt/nas/tv']],
                    ], [
                        'key' => '3',
                        'title' => 'Movies',
                        'type' => 'movie',
                        'Location' => [['path' => '/mnt/nas/movies']],
                    ]],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = new PlexClient(new Client(['handler' => HandlerStack::create($mock)]));
        $server = new MediaServer();
        $server->baseUrl = 'http://plex.test:32400';
        $server->apiToken = 'plex-token';

        $libraries = $client->fetchLibraries($server);

        expect($libraries)->toHaveCount(2)
            ->and($libraries[0]->externalId)->toBe('2')
            ->and($libraries[0]->name)->toBe('TV Shows')
            ->and($libraries[0]->libraryType)->toBe(MediaServerLibraryType::Tv)
            ->and($libraries[0]->remoteRoot)->toBe('/mnt/nas/tv')
            ->and($libraries[1]->libraryType)->toBe(MediaServerLibraryType::Movie);
    });

    it('throws when Plex returns a non-200 status', function (): void {
        $mock = new MockHandler([
            new Response(401, [], '{}'),
        ]);

        $client = new PlexClient(new Client(['handler' => HandlerStack::create($mock)]));
        $server = new MediaServer();
        $server->baseUrl = 'http://plex.test:32400';
        $server->apiToken = 'bad-token';

        $client->fetchLibraries($server);
    })->throws(MediaServerException::class);

    it('refreshes a library path via Plex API', function (): void {
        $mock = new MockHandler([
            new Response(200, [], '{}'),
        ]);

        $client = new PlexClient(new Client(['handler' => HandlerStack::create($mock)]));
        $server = new MediaServer();
        $server->baseUrl = 'http://plex.test:32400';
        $server->apiToken = 'plex-token';

        $client->refreshPath($server, '2', '/mnt/nas/tv/Show/episode.mp4');

        expect($mock->count())->toBe(0);
    });
});

describe('JellyfinClient', function (): void {
    it('maps virtual folders from Jellyfin JSON', function (): void {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                [
                    'ItemId' => 'abc123',
                    'Name' => 'Shows',
                    'CollectionType' => 'tvshows',
                    'Locations' => ['/media/shows'],
                ],
                [
                    'ItemId' => 'def456',
                    'Name' => 'Music',
                    'CollectionType' => 'music',
                    'Locations' => ['/media/music'],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = new JellyfinClient(new Client(['handler' => HandlerStack::create($mock)]));
        $server = new MediaServer();
        $server->baseUrl = 'http://jellyfin.test:8096';
        $server->apiToken = 'jellyfin-token';

        $libraries = $client->fetchLibraries($server);

        expect($libraries)->toHaveCount(2)
            ->and($libraries[0]->externalId)->toBe('abc123')
            ->and($libraries[0]->libraryType)->toBe(MediaServerLibraryType::Tv)
            ->and($libraries[0]->remoteRoot)->toBe('/media/shows')
            ->and($libraries[1]->libraryType)->toBe(MediaServerLibraryType::Music);
    });

    it('throws when Jellyfin returns a non-200 status', function (): void {
        $mock = new MockHandler([
            new Response(403, [], '{}'),
        ]);

        $client = new JellyfinClient(new Client(['handler' => HandlerStack::create($mock)]));
        $server = new MediaServer();
        $server->baseUrl = 'http://jellyfin.test:8096';
        $server->apiToken = 'bad-token';

        $client->fetchLibraries($server);
    })->throws(MediaServerException::class);

    it('posts a library refresh to Jellyfin', function (): void {
        $mock = new MockHandler([
            new Response(204, [], ''),
        ]);

        $client = new JellyfinClient(new Client(['handler' => HandlerStack::create($mock)]));
        $server = new MediaServer();
        $server->baseUrl = 'http://jellyfin.test:8096';
        $server->apiToken = 'jellyfin-token';

        $client->refreshLibrary($server, 'abc123');

        expect($mock->count())->toBe(0);
    });
});
