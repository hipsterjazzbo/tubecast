<?php

declare(strict_types=1);

namespace App\Config;

final readonly class TubecastConfig
{
    public function __construct(
        public string $dataPath,
        public string $downloadsPath,
        public string $podcastPath,
        public string $ytDlpBinary,
        public int $workerConcurrency,
        public float $sleepInterval,
        public float $sleepRequests,
        public ?string $limitRate,
    ) {
    }

    public function storedCommandsPath(): string
    {
        return rtrim($this->dataPath, '/') . '/stored-commands';
    }
}
