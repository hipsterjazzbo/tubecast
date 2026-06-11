<?php

declare(strict_types=1);

namespace App\Services\Setup;

use function Tempest\root_path;

final class EnvFileWriter
{
    public function setYtDlpBinaryIfDefault(string $relativePath): bool
    {
        $envPath = root_path('.env');

        if (!is_file($envPath)) {
            return false;
        }

        $contents = file_get_contents($envPath);

        if ($contents === false) {
            return false;
        }

        if (preg_match('/^YT_DLP_BINARY=(.+)$/m', $contents, $matches) === 1) {
            $current = trim($matches[1]);

            if ($current !== '' && $current !== 'yt-dlp') {
                return false;
            }
        }

        $line = 'YT_DLP_BINARY=' . $relativePath;

        if (preg_match('/^YT_DLP_BINARY=.*$/m', $contents) === 1) {
            $updated = preg_replace('/^YT_DLP_BINARY=.*$/m', $line, $contents);
        } else {
            $updated = rtrim($contents) . PHP_EOL . $line . PHP_EOL;
        }

        if (!is_string($updated)) {
            return false;
        }

        return file_put_contents($envPath, $updated) !== false;
    }
}
