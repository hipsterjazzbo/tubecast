<?php

declare(strict_types=1);

namespace App\Services\Podcast;

use App\Models\MediaItem;
use App\Models\MediaProfile;
use App\Config\TubecastConfig;
use Tempest\Process\GenericProcessExecutor;
use Tempest\Process\PendingProcess;

final class PodcastVariantService
{
    public function __construct(
        private TubecastConfig $config,
    ) {
    }

    public function generate(MediaItem $item, ?MediaProfile $profile = null): void
    {
        if ($item->filePath === null || ! is_file($item->filePath)) {
            return;
        }

        $bitrate = $profile?->podcastBitrateKbps ?? 96;
        $outputDir = $this->config->podcastPath . '/' . $item->sourceId;

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $outputPath = $outputDir . '/' . $item->ytId . '.m4a';

        $process = new PendingProcess(
            command: [
                'ffmpeg',
                '-y',                      // overwrite output without prompting
                '-i', $item->filePath,      // input file path
                '-vn',                     // strip video; audio-only output
                '-c:a', 'aac',             // encode audio stream as AAC
                '-b:a', $bitrate . 'k',    // target audio bitrate (kilobits/sec)
                '-ac', '1',                // downmix to mono for smaller podcast files
                $outputPath,               // output .m4a path
            ],
            timeout: 3600,
        );

        (new GenericProcessExecutor())->run($process)->assertSuccessful();

        $item->podcastFilePath = $outputPath;
        $item->podcastFileSize = filesize($outputPath) ?: null;
        $item->podcastMime = 'audio/mp4';
    }
}
