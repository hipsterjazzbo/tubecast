<?php

declare(strict_types=1);

use App\Enums\MediaItemStatus;
use App\Services\DownloadRecoveryService;
use App\Services\OutputPathBuilder;
use App\TubecastConfig;
use Tests\Support\Fixtures;

describe('Download recovery', function (): void {
    it('finalizes interrupted audio downloads from files on disk', function (): void {
        $root = sys_get_temp_dir() . '/tubecast-recover-' . uniqid('', true);
        $podcast = $root . '/podcast/1';
        mkdir($podcast, 0755, true);

        $ytId = 'abc123';
        file_put_contents($podcast . '/' . $ytId . '.m4a', str_repeat('a', 4096));

        $config = new TubecastConfig(
            dataPath: $root,
            downloadsPath: $root . '/downloads',
            podcastPath: $root . '/podcast',
            ytDlpBinary: 'yt-dlp',
            workerConcurrency: 1,
            sleepInterval: 0,
            sleepRequests: 0,
            limitRate: null,
        );

        $recovery = new DownloadRecoveryService(
            new OutputPathBuilder($config),
            $this->container->get(\App\Services\PodcastVariantService::class),
            $this->container->get(\Tempest\CommandBus\CommandBus::class),
            $this->container->get(\Tempest\Log\Logger::class),
        );

        $source = Fixtures::source(['saveVideo' => false, 'saveAudio' => true]);
        $item = Fixtures::mediaItem($source, [
            'ytId' => $ytId,
            'status' => MediaItemStatus::Downloading,
        ]);

        expect($recovery->finalizeFromDisk($source, $item, null))->toBeTrue()
            ->and($item->status)->toBe(MediaItemStatus::Completed)
            ->and($item->podcastFilePath)->toContain($ytId . '.m4a');

        array_map('unlink', glob($podcast . '/*') ?: []);
        rmdir($podcast);
        rmdir($root . '/podcast');
        rmdir($root);
    });

    it('resets stuck downloading items without files back to indexed', function (): void {
        $source = Fixtures::source(['saveVideo' => false, 'saveAudio' => true]);
        $item = Fixtures::mediaItem($source, [
            'ytId' => 'missing123',
            'status' => MediaItemStatus::Downloading,
        ]);

        $recovery = $this->container->get(DownloadRecoveryService::class);

        expect($recovery->prepareInterruptedDownload($source, $item, null))->toBeFalse()
            ->and($item->status)->toBe(MediaItemStatus::Indexed);
    });
});
