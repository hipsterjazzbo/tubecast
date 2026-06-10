<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Feed;
use App\Models\Source;
use App\Services\Podcast\RssFeedService;
use App\Services\Core\ModelId;
use Tempest\Http\Response;
use Tempest\Http\Responses\NotFound;
use Tempest\Http\Responses\Ok;
use Tempest\Router\Get;
use Tempest\Router\Stateless;

#[Stateless]
final readonly class FeedController
{
    public function __construct(
        private RssFeedService $rss,
    ) {
    }

    #[Get('/feeds/{token}/audio.xml')]
    public function audio(string $token): Response
    {
        $feed = $this->feedForToken($token);

        if ($feed === null || ! $this->sourceSavesAudio($feed)) {
            return new NotFound();
        }

        return (new Ok($this->rss->buildAudio($feed)))
            ->addHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    #[Get('/feeds/{token}/video.xml')]
    public function video(string $token): Response
    {
        $feed = $this->feedForToken($token);

        if ($feed === null || ! $this->sourceSavesVideo($feed)) {
            return new NotFound();
        }

        return (new Ok($this->rss->buildVideo($feed)))
            ->addHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    private function feedForToken(string $token): ?Feed
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

        return Source::findById(ModelId::int($feed->sourceId));
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
}
