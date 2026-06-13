<?php

declare(strict_types=1);

namespace App\Services\MediaServer;

use App\Enums\MediaServerLibraryType;
use App\Models\MediaServer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

final class PlexClient implements MediaServerClient
{
    public function __construct(
        private Client $http = new Client(['http_errors' => false, 'timeout' => 15]),
    ) {
    }

    public function testConnection(MediaServer $server): void
    {
        $this->fetchLibraries($server);
    }

    public function fetchLibraries(MediaServer $server): array
    {
        $response = $this->request($server, 'GET', '/library/sections');

        if ($response['status'] !== 200) {
            throw new MediaServerException('Plex connection failed: HTTP ' . $response['status']);
        }

        $libraries = [];
        $directories = $response['body']['MediaContainer']['Directory'] ?? [];

        if (! is_array($directories)) {
            return [];
        }

        if (array_key_exists('key', $directories)) {
            $directories = [$directories];
        }

        foreach ($directories as $directory) {
            if (! is_array($directory)) {
                continue;
            }

            $externalId = (string) ($directory['key'] ?? '');
            $name = (string) ($directory['title'] ?? 'Library');

            if ($externalId === '') {
                continue;
            }

            $remoteRoot = null;
            $location = $directory['Location'] ?? null;

            if (is_array($location)) {
                if (array_key_exists('path', $location)) {
                    $remoteRoot = (string) $location['path'];
                } else {
                    $first = $location[0] ?? null;
                    $remoteRoot = is_array($first) ? (string) ($first['path'] ?? '') : null;
                    $remoteRoot = $remoteRoot !== '' ? $remoteRoot : null;
                }
            }

            $libraries[] = new MediaServerLibraryDto(
                externalId: $externalId,
                name: $name,
                libraryType: $this->mapType((string) ($directory['type'] ?? 'show')),
                remoteRoot: $remoteRoot,
            );
        }

        return $libraries;
    }

    public function refreshPath(MediaServer $server, string $libraryExternalId, string $remotePath): void
    {
        $response = $this->request(
            $server,
            'GET',
            sprintf('/library/sections/%s/refresh', rawurlencode($libraryExternalId)),
            query: ['path' => $remotePath],
        );

        if ($response['status'] >= 400) {
            throw new MediaServerException('Plex path refresh failed: HTTP ' . $response['status']);
        }
    }

    public function refreshLibrary(MediaServer $server, string $libraryExternalId): void
    {
        $response = $this->request(
            $server,
            'GET',
            sprintf('/library/sections/%s/refresh', rawurlencode($libraryExternalId)),
        );

        if ($response['status'] >= 400) {
            throw new MediaServerException('Plex library refresh failed: HTTP ' . $response['status']);
        }
    }

    /** @param array<string, scalar|null> $query */
    private function request(MediaServer $server, string $method, string $path, array $query = []): array
    {
        $url = rtrim($server->baseUrl, '/') . $path;
        $query['X-Plex-Token'] = $server->apiToken;

        try {
            $response = $this->http->request($method, $url, [
                RequestOptions::QUERY => $query,
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (GuzzleException $exception) {
            throw new MediaServerException('Plex request failed: ' . $exception->getMessage(), previous: $exception);
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        return [
            'status' => $response->getStatusCode(),
            'body' => is_array($decoded) ? $decoded : [],
        ];
    }

    private function mapType(string $plexType): MediaServerLibraryType
    {
        return match (strtolower($plexType)) {
            'movie' => MediaServerLibraryType::Movie,
            'show' => MediaServerLibraryType::Tv,
            'artist' => MediaServerLibraryType::Music,
            default => MediaServerLibraryType::Other,
        };
    }
}
