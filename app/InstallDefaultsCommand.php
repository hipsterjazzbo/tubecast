<?php

declare(strict_types=1);

namespace App;

use App\Models\MediaProfile;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\HasConsole;

final readonly class InstallDefaultsCommand
{
    use HasConsole;

    #[ConsoleCommand('tubecast:install-defaults')]
    public function __invoke(): void
    {
        if (MediaProfile::count()->execute() === 0) {
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

            $this->success('Default media profiles installed.');
        }

        foreach (['data', 'data/downloads', 'data/podcast', 'data/config', 'data/stored-commands'] as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
}
