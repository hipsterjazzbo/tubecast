<?php

declare(strict_types=1);

namespace App\Services\MediaServer;

use App\Enums\MediaServerLibraryType;
use App\Models\MediaItem;
use App\Models\MediaServer;
use App\Models\MediaServerLibrary;
use App\Models\Source;
use App\Services\Core\ModelId;

final class MediaServerNotificationService
{
    public function __construct(
        private MediaServerClientFactory $clients,
        private MediaServerPathMapper $pathMapper,
        private MediaServerRefreshDebouncer $debouncer,
    ) {
    }

    public function notifyForCompletedItem(int $mediaItemId, int $sourceId): void
    {
        $source = Source::findById($sourceId);

        if ($source === null || ! $source->notifyMediaServer || $source->mediaServerLibraryId === null) {
            return;
        }

        $library = MediaServerLibrary::findById($source->mediaServerLibraryId);

        if ($library === null || ! $library->enabled) {
            return;
        }

        $server = MediaServer::findById($library->mediaServerId);

        if ($server === null || ! $server->enabled) {
            return;
        }

        $item = MediaItem::findById($mediaItemId);

        if ($item === null) {
            return;
        }

        $client = $this->clients->for($server);
        $libraryId = ModelId::int($library->id);

        if ($source->saveVideo && $item->filePath !== null) {
            $remotePath = $this->pathMapper->mapForSource($source, $server, $library, $item->filePath);

            if ($remotePath !== null && ! $this->debouncer->shouldSkip($libraryId, $remotePath)) {
                try {
                    $client->refreshPath($server, $library->externalId, $remotePath);
                    $this->debouncer->record($libraryId, $remotePath);

                    return;
                } catch (MediaServerException) {
                }
            }
        }

        if ($source->saveAudio && $item->podcastFilePath !== null && $library->libraryType === MediaServerLibraryType::Music) {
            $remotePath = $this->pathMapper->mapAudioPath($server, $item->podcastFilePath, $library);

            if ($remotePath !== null && ! $this->debouncer->shouldSkip($libraryId, $remotePath)) {
                try {
                    $client->refreshPath($server, $library->externalId, $remotePath);
                    $this->debouncer->record($libraryId, $remotePath);

                    return;
                } catch (MediaServerException) {
                }
            }
        }

        if (! $this->debouncer->shouldSkip($libraryId, 'library:' . $library->externalId)) {
            $client->refreshLibrary($server, $library->externalId);
            $this->debouncer->record($libraryId, 'library:' . $library->externalId);
        }
    }
}
