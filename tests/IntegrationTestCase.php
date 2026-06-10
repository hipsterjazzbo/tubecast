<?php

declare(strict_types=1);

namespace Tests;

use Tempest\Framework\Testing\IntegrationTest;

abstract class IntegrationTestCase extends IntegrationTest
{
    protected string $root = __DIR__ . '/../';

    protected function setUp(): void
    {
        putenv('ENVIRONMENT=testing');
        $_ENV['ENVIRONMENT'] = 'testing';
        $_SERVER['ENVIRONMENT'] = 'testing';

        parent::setUp();
    }

    /** @return \Tempest\Discovery\DiscoveryLocation[] */
    protected function discoverTestLocations(): array
    {
        // Do not discover Pest test files as Tempest components.
        return [];
    }
}
