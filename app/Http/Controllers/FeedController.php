<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Models\Source;
use App\Services\RssFeedService;
use App\Support\ModelId;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Responses\NotFound;
use Tempest\Http\Responses\Ok;
use Tempest\Router\Get;

final readonly class FeedController
{
    public function __construct(
        private RssFeedService $rss,
    ) {
    }

    #[Get('/feeds/{slug}/audio.xml')]
    public function audio(string $slug, Request $request): Response
    {
        $feed = $this->authorizedFeed($slug, $request);

        if ($feed === null || ! $this->sourceSavesAudio($feed)) {
            return new NotFound();
        }

        return (new Ok($this->rss->buildAudio($feed)))
            ->addHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    #[Get('/feeds/{slug}/video.xml')]
    public function video(string $slug, Request $request): Response
    {
        $feed = $this->authorizedFeed($slug, $request);

        if ($feed === null || ! $this->sourceSavesVideo($feed)) {
            return new NotFound();
        }

        return (new Ok($this->rss->buildVideo($feed)))
            ->addHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    private function authorizedFeed(string $slug, Request $request): ?Feed
    {
        $token = (string) $request->get('token', '');

        $feed = Feed::select()
            ->where('slug = ?', $slug)
            ->first();

        if ($feed === null || ! hash_equals($feed->token, (string) $token)) {
            return null;
        }

        return $feed;
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
