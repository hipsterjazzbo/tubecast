<?php

declare(strict_types=1);

use App\Enums\MediaItemStatus;
use App\Support\ModelId;
use App\TubecastConfig;
use Tests\Support\Fixtures;

describe('Media serving', function (): void {
    it('serves completed podcast audio via tokenized URL', function (): void {
        $config = $this->container->get(TubecastConfig::class);
        $source = Fixtures::source(['saveVideo' => false, 'saveAudio' => true]);
        $sourceId = ModelId::int($source->id);
        $podcast = rtrim($config->podcastPath, '/') . '/' . $sourceId;
        is_dir($podcast) || mkdir($podcast, 0755, true);

        $ytId = 'Qsi1kju8Exo';
        $file = $podcast . '/' . $ytId . '.m4a';
        file_put_contents($file, str_repeat('a', 4096));

        Fixtures::feed($source, ['token' => 'secret-token']);
        $item = Fixtures::mediaItem($source, [
            'ytId' => $ytId,
            'status' => MediaItemStatus::Completed,
        ]);
        $item->podcastFilePath = $file;
        $item->podcastFileSize = 4096;
        $item->podcastMime = 'audio/mp4';
        $item->save();

        $this->http->get('/media/secret-token/' . $ytId . '/audio.m4a')
            ->assertOk()
            ->assertHeaderContains('X-Accel-Redirect', '/internal-media/' . $sourceId . '/' . $ytId . '.m4a');

        unlink($file);
    });

    it('includes audio enclosure URLs in the RSS feed', function (): void {
        $config = $this->container->get(TubecastConfig::class);
        $source = Fixtures::source(['saveVideo' => false, 'saveAudio' => true]);
        $sourceId = ModelId::int($source->id);
        $podcast = rtrim($config->podcastPath, '/') . '/' . $sourceId;
        is_dir($podcast) || mkdir($podcast, 0755, true);

        $ytId = 'rss123';
        $file = $podcast . '/' . $ytId . '.m4a';
        file_put_contents($file, str_repeat('c', 1024));

        $feed = Fixtures::feed($source, ['token' => 'rss-feed-token']);
        $item = Fixtures::mediaItem($source, [
            'ytId' => $ytId,
            'status' => MediaItemStatus::Completed,
        ]);
        $item->podcastFilePath = $file;
        $item->podcastFileSize = 1024;
        $item->podcastMime = 'audio/mp4';
        $item->save();

        $this->http->get('/feeds/' . $feed->slug . '/audio.xml?token=rss-feed-token')
            ->assertOk()
            ->assertSee('/media/rss-feed-token/' . $ytId . '/audio.m4a', false);

        unlink($file);
    });
});
