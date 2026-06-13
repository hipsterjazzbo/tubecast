<?php

declare(strict_types=1);

namespace App\Services\MediaServer;

use App\Enums\MediaServerLibraryType;
use App\Models\MediaServer;
use App\Models\MediaServerLibrary;
use App\Services\Core\ModelId;

final class MediaServerLibraryQuery
{
    /** @return list<object{server: MediaServer, libraries: list<MediaServerLibrary>}> */
    public function enabledLibrariesGroupedByServer(): array
    {
        $servers = MediaServer::select()
            ->where('enabled = 1')
            ->all();

        $groups = [];

        foreach ($servers as $server) {
            $libraries = MediaServerLibrary::select()
                ->where('mediaServerId = ? AND enabled = 1', ModelId::int($server->id))
                ->all();

            if ($libraries === []) {
                continue;
            }

            $groups[] = (object) [
                'server' => $server,
                'libraries' => $libraries,
            ];
        }

        return $groups;
    }

    public function hasAnyEnabledLibraries(): bool
    {
        return MediaServerLibrary::count()
            ->where('enabled = 1')
            ->execute() > 0;
    }

    /** @return list<MediaServerLibrary> */
    public function librariesForSourceForm(bool $saveVideo, bool $saveAudio): array
    {
        $allowedTypes = [];

        if ($saveVideo) {
            $allowedTypes[] = MediaServerLibraryType::Movie->value;
            $allowedTypes[] = MediaServerLibraryType::Tv->value;
            $allowedTypes[] = MediaServerLibraryType::Other->value;
        }

        if ($saveAudio) {
            $allowedTypes[] = MediaServerLibraryType::Music->value;
        }

        if ($allowedTypes === []) {
            return [];
        }

        $libraries = [];

        foreach ($this->enabledLibrariesGroupedByServer() as $group) {
            foreach ($group->libraries as $library) {
                if (in_array($library->libraryType->value, $allowedTypes, true)) {
                    $libraries[] = $library;
                }
            }
        }

        return $libraries;
    }
}
