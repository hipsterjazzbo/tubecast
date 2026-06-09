<?php

declare(strict_types=1);

namespace App\Services;

use App\TubecastConfig;

final class ThrottleGuard
{
    private int $active = 0;

    public function __construct(
        private TubecastConfig $config,
    ) {
    }

    public function acquire(): void
    {
        while ($this->active >= $this->config->workerConcurrency) {
            usleep(250_000);
        }

        $this->active++;
    }

    public function release(): void
    {
        $this->active = max(0, $this->active - 1);
    }

    public function run(callable $callback): mixed
    {
        $this->acquire();

        try {
            return $callback();
        } finally {
            $this->release();
        }
    }
}
