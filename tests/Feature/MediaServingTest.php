<?php

declare(strict_types=1);

use App\Config\TubecastConfig;
use App\Enums\MediaItemStatus;
use App\Services\Core\ModelId;
use Tests\Support\Fixtures;

describe('Media serving', function (): void {
    it('serves completed audio via tokenized URL', function (): void {
        $config = $this->container->get(TubecastConfig::class);
        $source = Fixtures::source(['saveVideo' => false, 'saveAudio' => true]);
        $sourceId = ModelId::int($source->id);
        $audio = rtrim($config->audioPath, '/') . '/' . $sourceId;
        is_dir($audio) || mkdir($audio, 0755, true);

        $ytId = 'Qsi1kju8Exo';
        $file = $audio . '/' . $ytId . '.m4a';
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

        $this->logoutSession();

        $this->http->get('/media/secret-token/' . $ytId . '/audio.m4a')
            ->assertOk()
            ->assertHeaderContains('X-Accel-Redirect', 'audio/' . $sourceId . '/' . $ytId . '.m4a');

        unlink($file);
    });

    it('includes audio enclosure URLs in the RSS feed', function (): void {
        $config = $this->container->get(TubecastConfig::class);
        $source = Fixtures::source(['saveVideo' => false, 'saveAudio' => true]);
        $sourceId = ModelId::int($source->id);
        $audio = rtrim($config->audioPath, '/') . '/' . $sourceId;
        is_dir($audio) || mkdir($audio, 0755, true);

        $ytId = 'rss123';
        $file = $audio . '/' . $ytId . '.m4a';
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

        $this->logoutSession();

        $this->http->get('/feeds/rss-feed-token/audio.xml')
            ->assertOk()
            ->assertSee('/media/rss-feed-token/' . $ytId . '/audio.m4a', false);

        unlink($file);
    });
});
