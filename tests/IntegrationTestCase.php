<?php

declare(strict_types=1);

namespace Tests;

use App\Authentication\User;
use Tempest\Auth\Authentication\Authenticator;
use Tempest\Framework\Testing\Http\TestResponseHelper;
use Tempest\Framework\Testing\IntegrationTest;

abstract class IntegrationTestCase extends IntegrationTest
{
    public const string ADMIN_USERNAME = 'testadmin';

    public const string ADMIN_PASSWORD = 'test-admin-password';

    protected string $root = __DIR__ . '/../';

    protected string $authCookie = '';

    protected function setUp(): void
    {
        putenv('ENVIRONMENT=testing');
        $_ENV['ENVIRONMENT'] = 'testing';
        $_SERVER['ENVIRONMENT'] = 'testing';

        putenv('ADMIN_USERNAME=' . self::ADMIN_USERNAME);
        putenv('ADMIN_PASSWORD=' . self::ADMIN_PASSWORD);
        $_ENV['ADMIN_USERNAME'] = self::ADMIN_USERNAME;
        $_ENV['ADMIN_PASSWORD'] = self::ADMIN_PASSWORD;
        $_SERVER['ADMIN_USERNAME'] = self::ADMIN_USERNAME;
        $_SERVER['ADMIN_PASSWORD'] = self::ADMIN_PASSWORD;

        parent::setUp();
    }

    protected function ensureAdminUser(): void
    {
        $this->console->call('tubecast:ensure-admin')->assertSuccess();
    }

    protected function loginAsAdmin(): void
    {
        $this->ensureAdminUser();

        $user = User::select()
            ->where('username = ?', self::ADMIN_USERNAME)
            ->first();

        if ($user !== null) {
            $this->container->get(Authenticator::class)->authenticate($user);
        }
    }

    protected function logoutSession(): void
    {
        $this->container->get(Authenticator::class)->deauthenticate();
        $this->authCookie = '';
    }

    /** @param array<string, mixed> $query */
    protected function authedGet(string $uri, array $query = [], array $headers = []): TestResponseHelper
    {
        return $this->http->get($uri, $query, $this->withAuth($headers));
    }

    /** @param array<string, mixed> $body */
    protected function authedPost(string $uri, array|string $body = [], array $query = [], array $headers = []): TestResponseHelper
    {
        return $this->http->post($uri, $body, $query, $this->withAuth($headers));
    }

    /** @param array<string, mixed> $headers */
    protected function withAuth(array $headers = []): array
    {
        if ($this->authCookie !== '') {
            $headers['Cookie'] = $this->authCookie;
        }

        return $headers;
    }

    protected function extractCookieHeader(TestResponseHelper $response): string
    {
        $cookies = [];

        foreach ($response->headers as $name => $header) {
            if (strtolower($name) !== 'set-cookie') {
                continue;
            }

            foreach ($header->values as $value) {
                $cookies[] = trim(explode(';', $value)[0]);
            }
        }

        return implode('; ', $cookies);
    }

    /** @return \Tempest\Discovery\DiscoveryLocation[] */
    protected function discoverTestLocations(): array
    {
        // Do not discover Pest test files as Tempest components.
        return [];
    }
}
