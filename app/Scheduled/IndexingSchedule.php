<?php

declare(strict_types=1);

namespace App\Scheduled;

use App\Commands\FastIndexSourceCommand;
use App\Support\ModelId;
use App\Models\Source;
use Tempest\CommandBus\CommandBus;
use Tempest\Console\Schedule;
use Tempest\Console\Scheduler\Every;

final readonly class IndexingSchedule
{
    public function __construct(
        private CommandBus $commandBus,
    ) {
    }

    #[Schedule(Every::QUARTER)]
    public function fastIndexSources(): void
    {
        $sources = Source::select()
            ->where('enabled = 1')
            ->all();

        foreach ($sources as $source) {
            if ($source->youtubeRssUrl === null) {
                continue;
            }

            $this->commandBus->dispatch(new FastIndexSourceCommand(ModelId::int($source->id)));
        }
    }
}
