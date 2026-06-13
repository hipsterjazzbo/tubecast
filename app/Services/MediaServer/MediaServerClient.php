<?php

declare(strict_types=1);

namespace App\Services\MediaServer;

use App\Models\MediaServer;

interface MediaServerClient
{
    public function testConnection(MediaServer $server): void;

    /** @return list<MediaServerLibraryDto> */
    public function fetchLibraries(MediaServer $server): array;

    public function refreshPath(MediaServer $server, string $libraryExternalId, string $remotePath): void;

    public function refreshLibrary(MediaServer $server, string $libraryExternalId): void;
}
