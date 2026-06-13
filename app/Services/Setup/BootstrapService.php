<?php

declare(strict_types=1);

namespace App\Services\Setup;

use App\Config\TubecastConfig;
use App\Models\MediaProfile;

final class BootstrapService
{
    public function __construct(
        private TubecastConfig $config,
    )
    {
    }

    public function ensureDirectories(): int
    {
        $created = 0;

        foreach ($this->requiredDirectories() as $directory) {
            if (is_dir($directory)) {
                continue;
            }

            mkdir($directory, 0755, true);
            $created++;
        }

        return $created;
    }

    /** @return list<string> */
    public function requiredDirectories(): array
    {
        return array_values(array_unique([
            $this->config->dataPath,
            rtrim($this->config->dataPath, '/') . '/bin',
            rtrim($this->config->dataPath, '/') . '/config',
            $this->config->videoPath,
            $this->config->audioPath,
            $this->config->storedCommandsPath(),
        ]));
    }

    public function installDefaultProfiles(): bool
    {
        if (MediaProfile::count()->execute() > 0) {
            return false;
        }

        MediaProfile::create(
            name: 'Plex-friendly MP4',
            audioOnly: false,
            formatSelector: 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best',
            mergeFormat: 'mp4',
            sponsorblockRemove: true,
            podcastBitrateKbps: 96,
        );

        MediaProfile::create(
            name: 'Podcast audio',
            audioOnly: true,
            formatSelector: 'bestaudio/best',
            mergeFormat: 'm4a',
            sponsorblockRemove: true,
            podcastBitrateKbps: 96,
        );

        return true;
    }

    public function ytDlpInstallPath(): string
    {
        return rtrim($this->config->dataPath, '/') . '/bin/yt-dlp';
    }
}
