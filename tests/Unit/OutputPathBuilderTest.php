<?php

declare(strict_types=1);

use App\Services\Download\OutputPathBuilder;
use App\Config\TubecastConfig;

describe('OutputPathBuilder download progress', function (): void {
    it('tracks podcast directory files and info json for audio downloads', function (): void {
        $root = sys_get_temp_dir() . '/tubecast-progress-' . uniqid('', true);
        $downloads = $root . '/downloads';
        $podcast = $root . '/podcast/1';
        mkdir($downloads, 0755, true);
        mkdir($podcast, 0755, true);

        file_put_contents($podcast . '/abc123.info.json', json_encode([
            'id' => 'abc123',
            'filesize' => 1_000_000,
        ], JSON_THROW_ON_ERROR));
        file_put_contents($podcast . '/abc123.webm', str_repeat('a', 250_000));

        $builder = new OutputPathBuilder(new TubecastConfig(
            dataPath: $root,
            downloadsPath: $downloads,
            podcastPath: $root . '/podcast',
            ytDlpBinary: 'yt-dlp',
            workerConcurrency: 1,
            sleepInterval: 0,
            sleepRequests: 0,
            limitRate: null,
        ));

        expect($builder->receivedBytesForEpisode(1, 'abc123'))->toBe(250_000)
            ->and($builder->expectedBytesForEpisode(1, 'abc123', null))->toBe(1_000_000);

        array_map('unlink', glob($podcast . '/*') ?: []);
        rmdir($podcast);
        rmdir($root . '/podcast');
        rmdir($downloads);
        rmdir($root);
    });
});
