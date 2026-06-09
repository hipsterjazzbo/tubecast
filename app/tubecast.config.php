<?php

declare(strict_types=1);

use App\TubecastConfig;

use function Tempest\env;
use function Tempest\root_path;

$resolvePath = static function (string $path): string {
    if (str_starts_with($path, '/') || str_starts_with($path, ':')) {
        return $path;
    }

    return root_path($path);
};

return new TubecastConfig(
    dataPath: $resolvePath(env('DATA_PATH', 'data')),
    downloadsPath: $resolvePath(env('DOWNLOADS_PATH', 'data/downloads')),
    podcastPath: $resolvePath(env('PODCAST_PATH', 'data/podcast')),
    ytDlpBinary: env('YT_DLP_BINARY', 'yt-dlp'),
    workerConcurrency: (int) env('YT_DLP_WORKER_CONCURRENCY', '1'),
    sleepInterval: (float) env('YT_DLP_SLEEP_INTERVAL', '5'),
    sleepRequests: (float) env('YT_DLP_SLEEP_REQUESTS', '1'),
    limitRate: env('YT_DLP_LIMIT_RATE') ?: null,
);
