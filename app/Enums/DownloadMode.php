<?php

declare(strict_types=1);

namespace App\Enums;

enum DownloadMode: string
{
    case Auto = 'auto';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Automatic',
            self::Manual => 'Manual',
        };
    }
}
