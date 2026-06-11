<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Setup\BootstrapService;
use App\Services\Setup\DependencyChecker;
use App\Services\Setup\DependencyStatus;
use App\Services\Setup\EnvFileWriter;
use App\Services\Setup\YtDlpInstaller;
use Tempest\Console\ConsoleArgument;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;
use function Tempest\env;
use function Tempest\root_path;

final readonly class InitCommand
{
    use HasConsole;

    public function __construct(
        private BootstrapService  $bootstrap,
        private DependencyChecker $dependencies,
        private YtDlpInstaller    $ytDlpInstaller,
        private EnvFileWriter     $envFileWriter,
    )
    {
    }

    #[ConsoleCommand(
        name: 'tubecast:init',
        description: 'Initialize TubeCast: check/install dependencies, data directories, and defaults',
    )]
    public function __invoke(
        #[ConsoleArgument(description: 'Only check dependencies; exit non-zero if required tools are missing', aliases: ['--check'])]
        bool $check = false,
        #[ConsoleArgument(description: 'Skip dependency check and installation', aliases: ['--skip-deps'])]
        bool $skipDeps = false,
        #[ConsoleArgument(description: 'Re-download yt-dlp even if an existing binary works', aliases: ['--force-deps'])]
        bool $forceDeps = false,
        #[ConsoleArgument(description: 'Create or update the admin user from ADMIN_USERNAME and ADMIN_PASSWORD', aliases: ['--admin'])]
        bool $admin = false,
    ): ExitCode
    {
        $this->console->header('TubeCast init');

        if (!$skipDeps) {
            $depsOk = $this->handleDependencies($check, $forceDeps);

            if ($check) {
                return $depsOk ? ExitCode::SUCCESS : ExitCode::ERROR;
            }

            if (!$depsOk) {
                return ExitCode::ERROR;
            }
        } elseif ($check) {
            $this->console->error('--check cannot be combined with --skip-deps.');

            return ExitCode::ERROR;
        }

        $created = 0;

        $this->console->task(
            label: 'Ensuring data directories',
            handler: function () use (&$created): void {
                $created = $this->bootstrap->ensureDirectories();
            },
        );

        if ($created > 0) {
            $this->console->keyValue('Directories created', (string)$created);
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

        if ($admin || $this->adminCredentialsPresent()) {
            $result = $this->console->call('tubecast:ensure-admin');

            if ($result !== ExitCode::SUCCESS) {
                return $result;
            }
        } else {
            $this->console->info('Skipping admin user (set ADMIN_USERNAME and ADMIN_PASSWORD, or pass --admin).');
        }

        $this->console->success('TubeCast init complete.');

        return ExitCode::SUCCESS;
    }

    private function handleDependencies(bool $checkOnly, bool $forceDeps): bool
    {
        $statuses = $this->dependencies->checkAll();
        $this->reportStatuses($statuses);

        if ($this->dependencies->missingRequired()) {
            if ($checkOnly) {
                $this->reportMissingHints($statuses);

                return false;
            }

            if (!$statuses[0]->available || $forceDeps) {
                $this->installYtDlp($forceDeps);
                $statuses = $this->dependencies->checkAll();
                $this->reportStatuses($statuses);
            }
        }

        if ($this->dependencies->missingRequired()) {
            $this->reportMissingHints($statuses);
            $this->console->error('Required dependencies are missing.');

            return false;
        }

        return true;
    }

    /** @param list<DependencyStatus> $statuses */
    private function reportStatuses(array $statuses): void
    {
        foreach ($statuses as $status) {
            $label = $status->dependency->value . ($status->required() ? '' : ' (optional)');
            $state = $status->available
                ? '<style="bold fg-green">OK</style>'
                : ($status->required() ? '<style="bold fg-red">MISSING</style>' : '<style="fg-yellow">MISSING</style>');

            $details = $status->version ?? ($status->path ?? 'not found');
            $this->console->keyValue($label, $state . ' ' . $details);
        }
    }

    /** @param list<DependencyStatus> $statuses */
    private function reportMissingHints(array $statuses): void
    {
        foreach ($statuses as $status) {
            if ($status->available || !$status->required()) {
                continue;
            }

            $this->console->warning($status->dependency->value . ': ' . $status->dependency->installHint());
        }
    }

    private function installYtDlp(bool $force): void
    {
        $this->console->task(
            label: 'Installing yt-dlp',
            handler: function () use ($force): void {
                $this->ytDlpInstaller->install($force);
                $relative = $this->ytDlpEnvRelativePath();

                if ($relative !== null) {
                    $this->envFileWriter->setYtDlpBinaryIfDefault($relative);
                }
            },
        );
    }

    private function ytDlpEnvRelativePath(): ?string
    {
        $absolute = $this->bootstrap->ytDlpInstallPath();
        $root = rtrim(root_path(), '/') . '/';

        if (!str_starts_with($absolute, $root)) {
            return null;
        }

        return ltrim(substr($absolute, strlen($root)), '/');
    }

    private function adminCredentialsPresent(): bool
    {
        $username = trim((string)env('ADMIN_USERNAME', ''));
        $password = (string)env('ADMIN_PASSWORD', '');

        return $username !== '' && $password !== '';
    }
}
