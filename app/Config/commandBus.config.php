<?php

declare(strict_types=1);

use App\Repositories\WritableFileCommandRepository;
use Tempest\CommandBus\CommandBusConfig;

return new CommandBusConfig(
    commandRepositoryClass: WritableFileCommandRepository::class,
);
