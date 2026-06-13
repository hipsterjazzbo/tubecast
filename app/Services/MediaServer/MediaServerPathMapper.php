<?php

declare(strict_types=1);

namespace App\Services\MediaServer;

use App\Models\MediaServer;
use App\Models\MediaServerLibrary;
use App\Models\Source;

final class MediaServerPathMapper
{
    public function mapForSource(Source $source, MediaServer $server, MediaServerLibrary $library, string $tubecastPath): ?string
    {
        $remoteRoot = $library->remoteRoot;

        if ($remoteRoot === null || $remoteRoot === '') {
            return null;
        }

        return $this->mapPath(
            tubecastPath: $tubecastPath,
            tubecastRoot: rtrim($server->tubecastVideoRoot, '/'),
            remoteRoot: rtrim($remoteRoot, '/'),
        );
    }

    public function mapAudioPath(MediaServer $server, string $tubecastPath, MediaServerLibrary $library): ?string
    {
        $remoteRoot = $library->remoteRoot;

        if ($remoteRoot === null || $remoteRoot === '') {
            return null;
        }

        return $this->mapPath(
            tubecastPath: $tubecastPath,
            tubecastRoot: rtrim($server->tubecastAudioRoot, '/'),
            remoteRoot: rtrim($remoteRoot, '/'),
        );
    }

    private function mapPath(string $tubecastPath, string $tubecastRoot, string $remoteRoot): ?string
    {
        $normalizedPath = str_replace('\\', '/', $tubecastPath);
        $normalizedRoot = str_replace('\\', '/', $tubecastRoot);

        if (! str_starts_with($normalizedPath, $normalizedRoot)) {
            return null;
        }

        $relative = ltrim(substr($normalizedPath, strlen($normalizedRoot)), '/');

        return $remoteRoot . ($relative !== '' ? '/' . $relative : '');
    }
}
