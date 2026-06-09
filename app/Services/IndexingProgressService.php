<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\FastIndexSourceCommand;
use App\Commands\FullIndexSourceCommand;
use App\Models\Source;
use App\Support\ModelId;
use Tempest\CommandBus\CommandRepository;

final class IndexingProgressService
{
    public function __construct(
        private CommandRepository $commands,
    ) {
    }

    public function forSource(
        Source $source,
        int $episodeCount,
        int $matchedCount,
        int $filteredCount,
    ): IndexingProgress {
        $state = $this->pendingIndexState(ModelId::int($source->id));

        return new IndexingProgress(
            active: $state['pending'] > 0,
            pendingCommands: $state['pending'],
            fastIndexPending: $state['fast'] > 0,
            fullIndexPending: $state['full'] > 0,
            episodeCount: $episodeCount,
            matchedCount: $matchedCount,
            filteredCount: $filteredCount,
            expectedTotal: $source->catalogExpectedTotal,
            processedCount: $source->fullIndexProcessedCount,
            usingApi: $state['full'] > 0 && $source->catalogExpectedTotal !== null,
        );
    }

    /** @return array{pending: int, fast: int, full: int} */
    private function pendingIndexState(int $sourceId): array
    {
        $fast = 0;
        $full = 0;

        foreach ($this->commands->getPendingCommands() as $command) {
            if ($command instanceof FastIndexSourceCommand && $command->sourceId === $sourceId) {
                $fast++;
            }

            if ($command instanceof FullIndexSourceCommand && $command->sourceId === $sourceId) {
                $full++;
            }
        }

        return [
            'pending' => $fast + $full,
            'fast' => $fast,
            'full' => $full,
        ];
    }
}
