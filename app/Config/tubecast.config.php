<?php

declare(strict_types=1);

use App\Config\TubecastConfig;

use function Tempest\env;
use function Tempest\root_path;

$resolvePath = static function (string $path): string {
    if (str_starts_with($path, '/') || str_starts_with($path, ':')) {
        return $path;
    }

    return root_path($path);
};

$ytDlpBinary = env('YT_DLP_BINARY', 'yt-dlp');

if (str_contains($ytDlpBinary, '/') && ! str_starts_with($ytDlpBinary, '/') && ! str_starts_with($ytDlpBinary, ':')) {
    $ytDlpBinary = root_path($ytDlpBinary);
}

return new TubecastConfig(
    dataPath: $resolvePath(env('DATA_PATH', 'data')),
    videoPath: $resolvePath(env('VIDEO_PATH', 'data/video')),
    audioPath: $resolvePath(env('AUDIO_PATH', 'data/audio')),
    ytDlpBinary: $ytDlpBinary,
    workerConcurrency: (int) env('YT_DLP_WORKER_CONCURRENCY', '1'),
    sleepInterval: (float) env('YT_DLP_SLEEP_INTERVAL', '5'),
    sleepRequests: (float) env('YT_DLP_SLEEP_REQUESTS', '1'),
    limitRate: env('YT_DLP_LIMIT_RATE') ?: null,
);
