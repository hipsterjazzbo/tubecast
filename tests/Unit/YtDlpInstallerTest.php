<?php

declare(strict_types=1);

use App\Config\TubecastConfig;
use App\Services\Setup\BootstrapService;
use App\Services\Setup\YtDlpInstaller;

function tearDownTempDir(string $root): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($root);
}

describe('YtDlpInstaller', function (): void {
    it('writes a downloaded yt-dlp script to data/bin', function (): void {
        $root = sys_get_temp_dir() . '/tubecast-install-' . uniqid('', true);
        mkdir($root, 0755, true);

        $config = new TubecastConfig(
            dataPath: $root,
            videoPath: $root . '/video',
            audioPath: $root . '/audio',
            ytDlpBinary: 'yt-dlp',
            workerConcurrency: 1,
            sleepInterval: 0,
            sleepRequests: 0,
            limitRate: null,
        );

        $installer = new class (new BootstrapService($config)) extends YtDlpInstaller {
            protected function download(string $url): ?string
            {
                return "#!/usr/bin/env python3\nprint('test')\n";
            }
        };

        $path = $installer->install();

        expect($path)->toBe($root . '/bin/yt-dlp')
            ->and(is_executable($path))->toBeTrue()
            ->and(file_get_contents($path))->toContain('python3');

        tearDownTempDir($root);
    });
});
