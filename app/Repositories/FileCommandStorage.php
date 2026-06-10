<?php

declare(strict_types=1);

namespace App\Repositories;

use Tempest\CommandBus\Exceptions\PendingCommandCouldNotBeResolved;
use Tempest\Support\Filesystem;

use function Tempest\Support\arr;

/**
 * File-backed command queue storage. Same behaviour as Tempest's
 * {@see \Tempest\CommandBus\AsyncCommandRepositories\FileCommandRepository}
 * with a configurable directory (for Docker volume persistence).
 */
final readonly class FileCommandStorage
{
    public function __construct(
        private string $directory,
    ) {
    }

    public function store(string $uuid, object $command): void
    {
        Filesystem\write_file("{$this->directory}/{$uuid}.pending.txt", serialize($command));
    }

    public function findPendingCommand(string $uuid): object
    {
        $path = "{$this->directory}/{$uuid}.pending.txt";

        if (! Filesystem\is_file($path)) {
            throw new PendingCommandCouldNotBeResolved($uuid);
        }

        return unserialize(Filesystem\read_file($path), ['allowed_classes' => true]);
    }

    public function markAsDone(string $uuid): void
    {
        Filesystem\delete_file("{$this->directory}/{$uuid}.pending.txt");
    }

    public function markAsFailed(string $uuid): void
    {
        $pending = "{$this->directory}/{$uuid}.pending.txt";

        if (! Filesystem\is_file($pending)) {
            return;
        }

        rename(
            from: $pending,
            to: "{$this->directory}/{$uuid}.failed.txt",
        );
    }

    /** @return array<string, object> */
    public function getPendingCommands(): array
    {
        return arr(glob("{$this->directory}/*.pending.txt"))
            ->mapWithKeys(function (string $path) {
                if (! Filesystem\is_file($path)) {
                    return;
                }

                $uuid = str_replace('.pending.txt', '', pathinfo($path, PATHINFO_BASENAME));

                yield $uuid => unserialize(Filesystem\read_file($path), ['allowed_classes' => true]);
            })
            ->toArray();
    }
}
