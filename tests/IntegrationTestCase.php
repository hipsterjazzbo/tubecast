<?php

declare(strict_types=1);

namespace Tests;

use Tempest\Framework\Testing\IntegrationTest;

abstract class IntegrationTestCase extends IntegrationTest
{
    protected string $root = __DIR__ . '/../';

    /** @return \Tempest\Discovery\DiscoveryLocation[] */
    protected function discoverTestLocations(): array
    {
        // Do not discover Pest test files as Tempest components.
        return [];
    }
}
