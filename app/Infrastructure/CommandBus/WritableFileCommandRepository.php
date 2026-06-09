<?php

declare(strict_types=1);

namespace App\Infrastructure\CommandBus;

use App\TubecastConfig;
use Tempest\CommandBus\CommandRepository;
use Tempest\CommandBus\Exceptions\PendingCommandCouldNotBeResolved;
use Tempest\Support\Filesystem;

use function Tempest\Support\arr;

final readonly class WritableFileCommandRepository implements CommandRepository
{
    public function __construct(
        private TubecastConfig $config,
    ) {
    }

    private function directory(): string
    {
        return $this->config->storedCommandsPath();
    }

    public function store(string $uuid, object $command): void
    {
        $payload = serialize($command);

        Filesystem\write_file("{$this->directory()}/{$uuid}.pending.txt", $payload);
    }

    public function findPendingCommand(string $uuid): object
    {
        $path = "{$this->directory()}/{$uuid}.pending.txt";

        if (! Filesystem\is_file($path)) {
            throw new PendingCommandCouldNotBeResolved($uuid);
        }

        $payload = Filesystem\read_file($path);

        return unserialize($payload);
    }

    public function markAsDone(string $uuid): void
    {
        Filesystem\delete_file("{$this->directory()}/{$uuid}.pending.txt");
    }

    public function markAsFailed(string $uuid): void
    {
        $pending = "{$this->directory()}/{$uuid}.pending.txt";

        if (! Filesystem\is_file($pending)) {
            return;
        }

        rename(
            from: $pending,
            to: "{$this->directory()}/{$uuid}.failed.txt",
        );
    }

    public function getPendingCommands(): array
    {
        return arr(glob("{$this->directory()}/*.pending.txt"))
            ->mapWithKeys(function (string $path) {
                if (! Filesystem\is_file($path)) {
                    return;
                }

                $uuid = str_replace('.pending.txt', '', pathinfo($path, PATHINFO_BASENAME));
                $payload = Filesystem\read_file($path);

                yield $uuid => unserialize($payload);
            })
            ->toArray();
    }
}
