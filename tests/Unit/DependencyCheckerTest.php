<?php

declare(strict_types=1);

use App\Config\TubecastConfig;
use App\Services\Setup\BootstrapService;
use App\Services\Setup\DependencyChecker;
use App\Services\Setup\ExternalDependency;

describe('DependencyChecker', function (): void {
    it('detects an executable yt-dlp candidate by path', function (): void {
        $root = sys_get_temp_dir() . '/tubecast-deps-' . uniqid('', true);
        mkdir($root . '/bin', 0755, true);

        $script = $root . '/bin/yt-dlp';
        file_put_contents($script, "#!/bin/sh\necho '2026.01.01'\n");
        chmod($script, 0755);

        $config = new TubecastConfig(
            dataPath: $root,
            downloadsPath: $root . '/downloads',
            podcastPath: $root . '/podcast',
            ytDlpBinary: $script,
            workerConcurrency: 1,
            sleepInterval: 0,
            sleepRequests: 0,
            limitRate: null,
        );

        $checker = new DependencyChecker($config, new BootstrapService($config));
        $status = $checker->checkYtDlp();

        expect($status->available)->toBeTrue()
            ->and($status->path)->toBe($script)
            ->and($status->dependency)->toBe(ExternalDependency::YtDlp);

        unlink($script);
        rmdir($root . '/bin');
        rmdir($root);
    });

    it('reports missing required dependencies', function (): void {
        $root = sys_get_temp_dir() . '/tubecast-deps-' . uniqid('', true);
        mkdir($root, 0755, true);

        $config = new TubecastConfig(
            dataPath: $root,
            downloadsPath: $root . '/downloads',
            podcastPath: $root . '/podcast',
            ytDlpBinary: $root . '/bin/yt-dlp',
            workerConcurrency: 1,
            sleepInterval: 0,
            sleepRequests: 0,
            limitRate: null,
        );

        $checker = new DependencyChecker($config, new BootstrapService($config));

        expect($checker->missingRequired())->toBeTrue();

        rmdir($root);
    });
});
