<?php

declare(strict_types=1);

namespace App\Services\Setup;

use RuntimeException;

class YtDlpInstaller
{
    public const string RELEASE_URL = 'https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp';

    public function __construct(
        private BootstrapService $bootstrap,
    )
    {
    }

    public function install(bool $force = false): string
    {
        $target = $this->bootstrap->ytDlpInstallPath();

        if (!$force && is_executable($target)) {
            return $target;
        }

        $this->bootstrap->ensureDirectories();

        $payload = $this->download(self::RELEASE_URL);

        if ($payload === null || $payload === '') {
            throw new RuntimeException('Failed to download yt-dlp from ' . self::RELEASE_URL);
        }

        if (file_put_contents($target, $payload) === false) {
            throw new RuntimeException('Failed to write yt-dlp to ' . $target);
        }

        chmod($target, 0755);

        return $target;
    }

    protected function download(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'follow_location' => 1,
                'timeout' => 60,
                'user_agent' => 'tubecast-init',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $payload = @file_get_contents($url, false, $context);

        return $payload === false ? null : $payload;
    }
}
