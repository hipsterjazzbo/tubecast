<?php

declare(strict_types=1);

namespace App\Services\Setup;

enum ExternalDependency: string
{
    case YtDlp = 'yt-dlp';
    case Ffmpeg = 'ffmpeg';
    case Ffprobe = 'ffprobe';
    case Python3 = 'python3';
    case Deno = 'deno';

    public function required(): bool
    {
        return match ($this) {
            self::YtDlp, self::Ffmpeg, self::Python3 => true,
            self::Ffprobe, self::Deno => false,
        };
    }

    public function installHint(): string
    {
        return match ($this) {
            self::YtDlp => 'Run php tempest tubecast:init to download yt-dlp into data/bin, or install it system-wide.',
            self::Ffmpeg => 'Install ffmpeg (e.g. pacman -S ffmpeg, apt install ffmpeg, brew install ffmpeg).',
            self::Ffprobe => 'Usually ships with ffmpeg; install ffmpeg if ffprobe is missing.',
            self::Python3 => 'Install Python 3 (e.g. pacman -S python, apt install python3).',
            self::Deno => 'Optional for some yt-dlp extractors (e.g. pacman -S deno, brew install deno).',
        };
    }
}
