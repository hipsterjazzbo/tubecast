<?php

declare(strict_types=1);

use App\Infrastructure\CommandBus\WritableFileCommandRepository;
use Tempest\CommandBus\CommandBusConfig;

return new CommandBusConfig(
    commandRepositoryClass: WritableFileCommandRepository::class,
);
