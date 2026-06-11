<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Setup\BootstrapService;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;

final readonly class InstallDefaultsCommand
{
    use HasConsole;

    public function __construct(
        private BootstrapService $bootstrap,
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
                $created = $this->bootstrap->ensureDirectories();
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
                $installed = $this->bootstrap->installDefaultProfiles();
            },
        );

        if ($installed) {
            $this->console->keyValue('Media profiles', '<style="bold fg-green">INSTALLED</style>');
        } else {
            $this->console->info('Default media profiles already present.');
        }

        return ExitCode::SUCCESS;
    }
}
