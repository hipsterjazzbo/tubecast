<?php

declare(strict_types=1);

namespace App;

use App\Services\DownloadRecoveryService;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\HasConsole;

final readonly class RecoverDownloadsCommand
{
    use HasConsole;

    public function __construct(
        private DownloadRecoveryService $recovery,
    ) {
    }

    #[ConsoleCommand('tubecast:recover-downloads')]
    public function __invoke(): void
    {
        $result = $this->recovery->recoverAll();

        $this->success(sprintf(
            'Recovered %d interrupted download(s); re-queued %d source(s).',
            $result['finalized'],
            $result['enqueued'],
        ));
    }
}
