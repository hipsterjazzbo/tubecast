<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Download\DownloadRecoveryService;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;

final readonly class RecoverDownloadsCommand
{
    use HasConsole;

    public function __construct(
        private DownloadRecoveryService $recovery,
    ) {
    }

    #[ConsoleCommand(
        name: 'tubecast:recover-downloads',
        description: 'Finalize interrupted downloads and re-queue pending source work',
    )]
    public function __invoke(): ExitCode
    {
        $this->console->header('Download recovery');

        $result = $this->recovery->recoverAll();

        $this->console->keyValue('Interrupted downloads finalized', (string) $result['finalized']);
        $this->console->keyValue('Sources re-queued', (string) $result['enqueued']);

        if ($result['finalized'] === 0 && $result['enqueued'] === 0) {
            $this->console->info('Nothing to recover.');
        } else {
            $this->console->success('Download recovery complete.');
        }

        return ExitCode::SUCCESS;
    }
}
