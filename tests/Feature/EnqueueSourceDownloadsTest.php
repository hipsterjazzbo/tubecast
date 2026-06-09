<?php

declare(strict_types=1);

use App\Commands\DownloadMediaCommand;
use App\Commands\EnqueueSourceDownloadsCommand;
use App\Enums\MediaItemStatus;
use App\Support\ModelId;
use Tests\Support\Fixtures;

describe('Enqueue source downloads', function (): void {
    it('queues download commands for indexed episodes when audio is enabled', function (): void {
        $source = Fixtures::source(['saveVideo' => false, 'saveAudio' => true]);
        $item = Fixtures::mediaItem($source, [
            'ytId' => 'audio1',
            'status' => MediaItemStatus::Indexed,
        ]);

        $this->container->get(\App\Commands\Handlers\SourceCommandHandlers::class)
            ->handleEnqueueDownloads(new EnqueueSourceDownloadsCommand(ModelId::int($source->id)));

        $pending = $this->container->get(\Tempest\CommandBus\CommandRepository::class)->getPendingCommands();
        $downloadCommands = array_values(array_filter(
            $pending,
            fn ($command): bool => $command instanceof DownloadMediaCommand
                && $command->mediaItemId === ModelId::int($item->id),
        ));

        expect($downloadCommands)->toHaveCount(1);
    });

    it('does not queue downloads for filtered episodes', function (): void {
        $source = Fixtures::source(['saveVideo' => false, 'saveAudio' => true]);
        $item = Fixtures::mediaItem($source, [
            'ytId' => 'short1',
            'status' => MediaItemStatus::Filtered,
            'durationSeconds' => 30,
            'metadataJson' => json_encode([
                'id' => 'short1',
                'duration' => 30,
                'webpage_url' => 'https://www.youtube.com/shorts/short1',
                'live_status' => 'not_live',
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->container->get(\App\Commands\Handlers\SourceCommandHandlers::class)
            ->handleEnqueueDownloads(new EnqueueSourceDownloadsCommand(ModelId::int($source->id)));

        $pending = $this->container->get(\Tempest\CommandBus\CommandRepository::class)->getPendingCommands();
        $itemId = ModelId::int($item->id);

        expect(array_filter(
            $pending,
            fn ($command): bool => $command instanceof DownloadMediaCommand && $command->mediaItemId === $itemId,
        ))->toBeEmpty();
    });
});

describe('Settings save triggers downloads', function (): void {
    it('enqueues downloads after enabling audio on an index-only source', function (): void {
        $source = Fixtures::source(['saveVideo' => false, 'saveAudio' => false]);
        Fixtures::mediaItem($source, ['ytId' => 'ep1', 'status' => MediaItemStatus::Indexed]);
        $sourceId = ModelId::int($source->id);

        $this->http->post('/sources/' . $sourceId . '/settings', [
            'saveAudio' => '1',
            'downloadMode' => 'auto',
        ])->assertRedirect('/sources/' . $sourceId);

        $pending = $this->container->get(\Tempest\CommandBus\CommandRepository::class)->getPendingCommands();

        expect(array_filter(
            $pending,
            fn ($command): bool => $command instanceof EnqueueSourceDownloadsCommand
                && $command->sourceId === $sourceId,
        ))->not->toBeEmpty();
    });
});
