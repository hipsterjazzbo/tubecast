<?php

declare(strict_types=1);

namespace App\Services\Download;

final readonly class DownloadProgress
{
    public readonly string $barClass;

    public function __construct(
        public bool $active,
        public ?int $percent,
        public ?int $bytesReceived,
        public ?int $bytesTotal,
        public bool $indeterminate,
    ) {
        $this->barClass = $indeterminate ? 'tc-bar-indeterminate bg-sky-400' : '';
    }

    public function widthStyle(): string
    {
        if ($this->indeterminate) {
            return '';
        }

        return 'width: ' . max(0, min(100, $this->percent ?? 0)) . '%';
    }

    public function label(): ?string
    {
        if (! $this->active) {
            return null;
        }

        if ($this->bytesReceived !== null && $this->bytesTotal !== null && $this->bytesTotal > 0) {
            return self::formatBytes($this->bytesReceived) . ' / ' . self::formatBytes($this->bytesTotal);
        }

        if ($this->bytesReceived !== null && $this->bytesReceived > 0) {
            return self::formatBytes($this->bytesReceived) . ' received';
        }

        if ($this->percent !== null) {
            return $this->percent . '%';
        }

        return 'Starting…';
    }

    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, $unit > 0 ? 1 : 0) . ' ' . $units[$unit];
    }
}
