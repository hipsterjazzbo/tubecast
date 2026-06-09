<?php

declare(strict_types=1);

use App\Commands\EnqueueSourceDownloadsCommand;
use App\Commands\FullIndexSourceCommand;
use App\Commands\ReapplyEpisodeFiltersCommand;
use App\Services\EpisodeFilterService;
use App\Services\SourceIndexingTriggers;
use App\Support\SourceFilters;
use Tests\Support\Fixtures;

describe('SourceIndexingTriggers', function (): void {
    beforeEach(function (): void {
        $this->triggers = new SourceIndexingTriggers(new EpisodeFilterService());
    });

    it('queues full index when include shorts is enabled', function (): void {
        $source = Fixtures::source(['includeShorts' => true]);

        $commands = $this->triggers->commandsAfterSettingsChange(
            $source,
            false,
            false,
            false,
            false,
            new SourceFilters(),
            new SourceFilters(),
        );

        expect($commands)->toHaveCount(1)
            ->and($commands[0])->toBeInstanceOf(FullIndexSourceCommand::class);
    });

    it('queues full index when include live is enabled', function (): void {
        $source = Fixtures::source(['includeLive' => true]);

        $commands = $this->triggers->commandsAfterSettingsChange(
            $source,
            false,
            false,
            false,
            false,
            new SourceFilters(),
            new SourceFilters(),
        );

        expect($commands)->toHaveCount(1)
            ->and($commands[0])->toBeInstanceOf(FullIndexSourceCommand::class);
    });

    it('queues filter reapply when duration rules change', function (): void {
        $source = Fixtures::source();
        $previous = new SourceFilters(minDurationSeconds: 600);
        $next = new SourceFilters(minDurationSeconds: 900);

        $commands = $this->triggers->commandsAfterSettingsChange(
            $source,
            false,
            false,
            false,
            false,
            $previous,
            $next,
        );

        expect($commands)->toHaveCount(1)
            ->and($commands[0])->toBeInstanceOf(ReapplyEpisodeFiltersCommand::class);
    });

    it('prefers full index over reapply when content flags also changed', function (): void {
        $source = Fixtures::source(['includeLive' => true]);
        $previous = new SourceFilters(minDurationSeconds: 600);
        $next = new SourceFilters(minDurationSeconds: 900);

        $commands = $this->triggers->commandsAfterSettingsChange(
            $source,
            false,
            false,
            false,
            false,
            $previous,
            $next,
        );

        expect($commands)->toHaveCount(1)
            ->and($commands[0])->toBeInstanceOf(FullIndexSourceCommand::class);
    });

    it('queues downloads when audio saving is enabled', function (): void {
        $source = Fixtures::source(['saveVideo' => false, 'saveAudio' => true]);

        $commands = $this->triggers->commandsAfterSettingsChange(
            $source,
            false,
            false,
            false,
            false,
            new SourceFilters(),
            new SourceFilters(),
        );

        expect($commands)->toHaveCount(1)
            ->and($commands[0])->toBeInstanceOf(EnqueueSourceDownloadsCommand::class);
    });

    it('queues downloads when switching from manual to auto mode', function (): void {
        $source = Fixtures::source(['saveAudio' => true]);
        $previous = SourceFilters::fromJson('{"downloadMode":"manual"}');
        $next = SourceFilters::fromJson('{"downloadMode":"auto"}');

        $commands = $this->triggers->commandsAfterSettingsChange(
            $source,
            false,
            false,
            true,
            true,
            $previous,
            $next,
        );

        expect($commands)->toHaveCount(1)
            ->and($commands[0])->toBeInstanceOf(EnqueueSourceDownloadsCommand::class);
    });

    it('queues nothing when only download mode changes to manual', function (): void {
        $source = Fixtures::source();
        $previous = SourceFilters::fromJson('{"downloadMode":"auto"}');
        $next = SourceFilters::fromJson('{"downloadMode":"manual"}');

        $commands = $this->triggers->commandsAfterSettingsChange(
            $source,
            false,
            false,
            true,
            false,
            $previous,
            $next,
        );

        expect($commands)->toBeEmpty();
    });
});
