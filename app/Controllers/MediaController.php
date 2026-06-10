<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Enums\MediaItemStatus;
use App\Models\Feed;
use App\Models\MediaItem;
use App\Models\Source;
use App\Config\TubecastConfig;
use Tempest\Http\GenericResponse;
use Tempest\Http\Response;
use Tempest\Http\Responses\NotFound;
use Tempest\Http\Status;
use Tempest\Router\Get;

final readonly class MediaController
{
    public function __construct(
        private TubecastConfig $config,
    ) {
    }

    #[Get('/media/{token}/{videoId}/file')]
    public function serveArchive(string $token, string $videoId): Response
    {
        $feed = $this->authorizedFeed($token);

        if ($feed === null || ! $this->sourceSavesVideo($feed)) {
            return new NotFound();
        }

        $item = $this->completedItem($feed, $videoId);

        if ($item === null || $item->filePath === null || ! is_file($item->filePath)) {
            return new NotFound();
        }

        $internalUri = $this->internalMediaUri($item->filePath, $this->config->downloadsPath, '/internal-downloads/');

        if ($internalUri === null) {
            return new NotFound();
        }

        return new GenericResponse(
            status: Status::OK,
            body: '',
            headers: [
                'X-Accel-Redirect' => $internalUri,
                'Content-Type' => 'video/mp4',
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ],
        );
    }

    #[Get('/media/{token}/{videoId}/audio.m4a')]
    public function serve(string $token, string $videoId): Response
    {
        return $this->serveAudio($token, $videoId);
    }

    private function serveAudio(string $token, string $videoId): Response
    {
        $feed = $this->authorizedFeed($token);

        if ($feed === null || ! $this->sourceSavesAudio($feed)) {
            return new NotFound();
        }

        $item = $this->completedItem($feed, $videoId);

        if ($item === null || $item->podcastFilePath === null || ! is_file($item->podcastFilePath)) {
            return new NotFound();
        }

        $internalUri = $this->internalMediaUri($item->podcastFilePath, $this->config->podcastPath, '/internal-media/');

        if ($internalUri === null) {
            return new NotFound();
        }

        return new GenericResponse(
            status: Status::OK,
            body: '',
            headers: [
                'X-Accel-Redirect' => $internalUri,
                'Content-Type' => $item->podcastMime ?? 'audio/mp4',
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ],
        );
    }

    private function authorizedFeed(string $token): ?Feed
    {
        return Feed::select()
            ->where('token = ?', $token)
            ->first();
    }

    private function sourceFor(Feed $feed): ?Source
    {
        if ($feed->sourceId === null) {
            return null;
        }

        return Source::findById($feed->sourceId);
    }

    private function sourceSavesAudio(Feed $feed): bool
    {
        $source = $this->sourceFor($feed);

        return $source !== null && $source->saveAudio;
    }

    private function sourceSavesVideo(Feed $feed): bool
    {
        $source = $this->sourceFor($feed);

        return $source !== null && $source->saveVideo;
    }

    private function completedItem(Feed $feed, string $videoId): ?MediaItem
    {
        $query = MediaItem::select()
            ->where('ytId = ?', $videoId)
            ->where('status = ?', MediaItemStatus::Completed->value);

        if ($feed->sourceId !== null) {
            $query = $query->where('sourceId = ?', $feed->sourceId);
        }

        return $query->first();
    }

    private function internalMediaUri(string $absolutePath, string $rootPath, string $prefix): ?string
    {
        $root = realpath($rootPath);
        $file = realpath($absolutePath);

        if ($root === false || $file === false || ! str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        $relative = ltrim(substr($file, strlen($root)), DIRECTORY_SEPARATOR);

        return $prefix . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }
}
