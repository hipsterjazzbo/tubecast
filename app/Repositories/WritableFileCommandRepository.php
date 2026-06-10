<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\TubecastConfig;
use Tempest\CommandBus\CommandRepository;

final readonly class WritableFileCommandRepository implements CommandRepository
{
    private FileCommandStorage $storage;

    public function __construct(TubecastConfig $config)
    {
        $this->storage = new FileCommandStorage($config->storedCommandsPath());
    }

    public function store(string $uuid, object $command): void
    {
        $this->storage->store($uuid, $command);
    }

    public function findPendingCommand(string $uuid): object
    {
        return $this->storage->findPendingCommand($uuid);
    }

    public function markAsDone(string $uuid): void
    {
        $this->storage->markAsDone($uuid);
    }

    public function markAsFailed(string $uuid): void
    {
        $this->storage->markAsFailed($uuid);
    }

    public function getPendingCommands(): array
    {
        return $this->storage->getPendingCommands();
    }
}
