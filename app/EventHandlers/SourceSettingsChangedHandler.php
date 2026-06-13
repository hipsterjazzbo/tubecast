<?php

declare(strict_types=1);

namespace App\EventHandlers;

use App\Commands\EnqueueSourceDownloadsCommand;
use App\Commands\FullIndexSourceCommand;
use App\Commands\ReapplyEpisodeFiltersCommand;
use App\Events\SourceSettingsUpdated;
use App\Models\Source;
use App\Services\Core\ModelId;
use App\Services\Source\SourceIndexingTriggers;
use Tempest\CommandBus\CommandBus;
use Tempest\EventBus\EventHandler;

final readonly class SourceSettingsChangedHandler
{
    public function __construct(
        private SourceIndexingTriggers $triggers,
        private CommandBus $commandBus,
    ) {
    }

    #[EventHandler]
    public function __invoke(SourceSettingsUpdated $event): void
    {
        $source = Source::findById($event->sourceId);

        if ($source === null) {
            return;
        }

        foreach ($this->triggers->commandsAfterSettingsChange(
            $source,
            $event->previousIncludeShorts,
            $event->previousIncludeLive,
            $event->previousSaveVideo,
            $event->previousSaveAudio,
            $event->previousFilters,
            $event->newFilters,
        ) as $command) {
            $this->commandBus->dispatch($command);
        }
    }
}
