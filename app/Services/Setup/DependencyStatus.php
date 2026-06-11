<?php

declare(strict_types=1);

namespace App\Services\Setup;

final readonly class DependencyStatus
{
    public function __construct(
        public ExternalDependency $dependency,
        public bool               $available,
        public ?string            $path,
        public ?string            $version,
    )
    {
    }

    public function required(): bool
    {
        return $this->dependency->required();
    }
}
