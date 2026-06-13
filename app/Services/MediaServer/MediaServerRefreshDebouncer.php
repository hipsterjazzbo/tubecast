<?php

declare(strict_types=1);

namespace App\Services\MediaServer;

use App\Repositories\SettingsRepository;

final class MediaServerRefreshDebouncer
{
    private const int DEBOUNCE_SECONDS = 45;

    public function __construct(
        private SettingsRepository $settings,
    ) {
    }

    public function shouldSkip(int $libraryId, string $remotePath): bool
    {
        $key = $this->key($libraryId, $remotePath);
        $last = $this->settings->get($key);

        if ($last === null || $last === '') {
            return false;
        }

        $timestamp = (int) $last;

        return (time() - $timestamp) < self::DEBOUNCE_SECONDS;
    }

    public function record(int $libraryId, string $remotePath): void
    {
        $this->settings->set($this->key($libraryId, $remotePath), (string) time());
    }

    private function key(int $libraryId, string $remotePath): string
    {
        return 'mediaServerRefresh_' . $libraryId . '_' . sha1($remotePath);
    }
}
