<?php

declare(strict_types=1);

namespace App\Services\MediaServer;

use App\Models\MediaServer;
use App\Models\MediaServerLibrary;
use App\Services\Core\ModelId;
use Tempest\DateTime\DateTime;

final class MediaServerSyncService
{
    public function __construct(
        private MediaServerClientFactory $clients,
    ) {
    }

    public function sync(MediaServer $server): int
    {
        $client = $this->clients->for($server);
        $remoteLibraries = $client->fetchLibraries($server);
        $seenExternalIds = [];

        foreach ($remoteLibraries as $remote) {
            $seenExternalIds[] = $remote->externalId;

            $existing = MediaServerLibrary::select()
                ->where('mediaServerId = ? AND externalId = ?', ModelId::int($server->id), $remote->externalId)
                ->first();

            if ($existing !== null) {
                $existing->name = $remote->name;
                $existing->libraryType = $remote->libraryType;
                $existing->remoteRoot = $remote->remoteRoot;
                $existing->enabled = true;
                $existing->save();

                continue;
            }

            MediaServerLibrary::create(
                mediaServerId: ModelId::int($server->id),
                externalId: $remote->externalId,
                name: $remote->name,
                libraryType: $remote->libraryType,
                remoteRoot: $remote->remoteRoot,
                enabled: true,
            );
        }

        foreach (MediaServerLibrary::select()->where('mediaServerId = ?', ModelId::int($server->id))->all() as $library) {
            if (! in_array($library->externalId, $seenExternalIds, true)) {
                $library->enabled = false;
                $library->save();
            }
        }

        $server->lastSyncedAt = DateTime::now();
        $server->lastSyncError = null;
        $server->save();

        return count($remoteLibraries);
    }
}
