<?php

declare(strict_types=1);

namespace Tests\Support;

use Symfony\Component\Process\Process;

final class E2eNetwork
{
    public static function isYouTubeReachable(): bool
    {
        static $available = null;

        if ($available !== null) {
            return $available;
        }

        $process = new Process([
            'curl',
            '-sf',
            '--max-time',
            '3',
            'https://www.youtube.com/feeds/videos.xml?channel_id=UCpXBGqwsBkpvcYjsJBQ7LEQ',
        ]);
        $process->setTimeout(5);
        $process->run();

        $available = $process->isSuccessful();

        return $available;
    }
}
