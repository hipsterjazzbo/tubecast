<?php

declare(strict_types=1);

use App\Config\TubecastConfig;
use App\Services\Download\OutputPathBuilder;

describe('OutputPathBuilder download progress', function (): void {
    it('tracks audio directory files and info json for audio downloads', function (): void {
        $root = sys_get_temp_dir() . '/tubecast-progress-' . uniqid('', true);
        $video = $root . '/video';
        $audio = $root . '/audio/1';
        mkdir($video, 0755, true);
        mkdir($audio, 0755, true);

        file_put_contents($audio . '/abc123.info.json', json_encode([
            'id' => 'abc123',
            'filesize' => 1_000_000,
        ], JSON_THROW_ON_ERROR));
        file_put_contents($audio . '/abc123.webm', str_repeat('a', 250_000));

        $builder = new OutputPathBuilder(new TubecastConfig(
            dataPath: $root,
            videoPath: $video,
            audioPath: $root . '/audio',
            ytDlpBinary: 'yt-dlp',
            workerConcurrency: 1,
            sleepInterval: 0,
            sleepRequests: 0,
            limitRate: null,
        ));

        expect($builder->receivedBytesForEpisode(1, 'abc123'))->toBe(250_000)
            ->and($builder->expectedBytesForEpisode(1, 'abc123', null))->toBe(1_000_000);

        array_map('unlink', glob($audio . '/*') ?: []);
        rmdir($audio);
        rmdir($root . '/audio');
        rmdir($video);
        rmdir($root);
    });
});
