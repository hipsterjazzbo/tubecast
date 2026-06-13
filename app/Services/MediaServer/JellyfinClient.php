<?php

declare(strict_types=1);

namespace App\Services\MediaServer;

use App\Enums\MediaServerLibraryType;
use App\Models\MediaServer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

final class JellyfinClient implements MediaServerClient
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
        $response = $this->request($server, 'GET', '/Library/VirtualFolders');

        if ($response['status'] !== 200) {
            throw new MediaServerException('Jellyfin connection failed: HTTP ' . $response['status']);
        }

        if (! is_array($response['body'])) {
            return [];
        }

        $libraries = [];

        foreach ($response['body'] as $folder) {
            if (! is_array($folder)) {
                continue;
            }

            $externalId = (string) ($folder['ItemId'] ?? $folder['Name'] ?? '');
            $name = (string) ($folder['Name'] ?? 'Library');

            if ($externalId === '') {
                continue;
            }

            $remoteRoot = null;
            $locations = $folder['Locations'] ?? [];

            if (is_array($locations) && $locations !== []) {
                $remoteRoot = (string) $locations[0];
            }

            $collectionType = strtolower((string) ($folder['CollectionType'] ?? ''));

            $libraries[] = new MediaServerLibraryDto(
                externalId: $externalId,
                name: $name,
                libraryType: $this->mapType($collectionType),
                remoteRoot: $remoteRoot !== '' ? $remoteRoot : null,
            );
        }

        return $libraries;
    }

    public function refreshPath(MediaServer $server, string $libraryExternalId, string $remotePath): void
    {
        $this->refreshLibrary($server, $libraryExternalId);
    }

    public function refreshLibrary(MediaServer $server, string $libraryExternalId): void
    {
        $response = $this->request($server, 'POST', '/Library/Media/Updated', [
            'Updates' => [
                ['Path' => $libraryExternalId, 'UpdateType' => 'Created'],
            ],
        ]);

        if ($response['status'] >= 400) {
            throw new MediaServerException('Jellyfin library refresh failed: HTTP ' . $response['status']);
        }
    }

    /** @param array<string, mixed> $json */
    private function request(MediaServer $server, string $method, string $path, array $json = []): array
    {
        $url = rtrim($server->baseUrl, '/') . $path;

        $options = [
            RequestOptions::HEADERS => [
                'X-Emby-Token' => $server->apiToken,
                'Accept' => 'application/json',
            ],
        ];

        if ($json !== []) {
            $options[RequestOptions::JSON] = $json;
        }

        try {
            $response = $this->http->request($method, $url, $options);
        } catch (GuzzleException $exception) {
            throw new MediaServerException('Jellyfin request failed: ' . $exception->getMessage(), previous: $exception);
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        return [
            'status' => $response->getStatusCode(),
            'body' => $decoded,
        ];
    }

    private function mapType(string $collectionType): MediaServerLibraryType
    {
        return match ($collectionType) {
            'movies' => MediaServerLibraryType::Movie,
            'tvshows' => MediaServerLibraryType::Tv,
            'music' => MediaServerLibraryType::Music,
            default => MediaServerLibraryType::Other,
        };
    }
}
