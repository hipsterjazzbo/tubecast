<?php

declare(strict_types=1);

use Tests\IntegrationTestCase;

describe('Authentication', function (): void {
    it('redirects unauthenticated users to login', function (): void {
        $this->logoutSession();

        $this->http->get('/')
            ->assertRedirect('/login');
    });

    it('renders the login page without authentication', function (): void {
        $this->logoutSession();

        $this->http->get('/login')
            ->assertOk()
            ->assertSee('Sign in');
    });

    it('rejects invalid credentials', function (): void {
        $this->logoutSession();
        $this->ensureAdminUser();

        $this->http->post('/login', [
            'username' => IntegrationTestCase::ADMIN_USERNAME,
            'password' => 'wrong-password',
        ])->assertRedirect('/login?error=1');
    });

    it('logs in with valid credentials', function (): void {
        $this->logoutSession();
        $this->ensureAdminUser();

        $this->http->post('/login', [
            'username' => IntegrationTestCase::ADMIN_USERNAME,
            'password' => IntegrationTestCase::ADMIN_PASSWORD,
        ])->assertRedirect('/');

        $this->loginAsAdmin();

        $this->authedGet('/')
            ->assertOk()
            ->assertSee('Dashboard');
    });

    it('logs out and returns to login', function (): void {
        $this->authedPost('/logout')
            ->assertRedirect('/login');

        $this->logoutSession();

        $this->http->get('/sources')
            ->assertRedirect('/login');
    });
});
