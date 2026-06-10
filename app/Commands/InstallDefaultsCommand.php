<?php

declare(strict_types=1);

namespace App\Commands;

use App\Config\TubecastConfig;
use App\Models\MediaProfile;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;

final readonly class InstallDefaultsCommand
{
    use HasConsole;

    public function __construct(
        private TubecastConfig $config,
    ) {
    }

    #[ConsoleCommand(
        name: 'tubecast:install-defaults',
        description: 'Install default media profiles and ensure data directories exist',
    )]
    public function __invoke(): ExitCode
    {
        $this->console->header('Tubecast setup');

        $created = 0;

        $this->console->task(
            label: 'Ensuring data directories',
            handler: function () use (&$created): void {
                $created = $this->ensureDirectories();
            },
        );

        if ($created > 0) {
            $this->console->keyValue('Directories created', (string) $created);
        } else {
            $this->console->info('All data directories already exist.');
        }

        $installed = false;

        $this->console->task(
            label: 'Installing default media profiles',
            handler: function () use (&$installed): void {
                $installed = $this->installProfiles();
            },
        );

        if ($installed) {
            $this->console->keyValue('Media profiles', '<style="bold fg-green">INSTALLED</style>');
        } else {
            $this->console->info('Default media profiles already present.');
        }

        return ExitCode::SUCCESS;
    }

    private function ensureDirectories(): int
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

    private function installProfiles(): bool
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

    /** @return list<string> */
    private function requiredDirectories(): array
    {
        return array_values(array_unique([
            $this->config->dataPath,
            rtrim($this->config->dataPath, '/') . '/config',
            $this->config->downloadsPath,
            $this->config->podcastPath,
            $this->config->storedCommandsPath(),
        ]));
    }
}
