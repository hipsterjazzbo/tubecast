<?php

declare(strict_types=1);

use Tests\IntegrationTestCase;

/*
|--------------------------------------------------------------------------
| Integration tests (HTTP + database)
|--------------------------------------------------------------------------
*/

pest()->extend(IntegrationTestCase::class)
    ->beforeEach(function (): void {
        $this->useTestingDatabase();
        $this->database->reset();

        $directory = dirname(__DIR__) . '/data/stored-commands';
        foreach (glob($directory . '/*.pending.txt') ?: [] as $path) {
            unlink($path);
        }
    })
    ->in('Feature', 'Ui');

pest()->extend(IntegrationTestCase::class)
    ->beforeEach(function (): void {
        $this->useTestingDatabase();
        $this->database->reset();

        $directory = dirname(__DIR__) . '/data/stored-commands';
        foreach (glob($directory . '/*.pending.txt') ?: [] as $path) {
            unlink($path);
        }

        if (getenv('TUBECAST_E2E') !== '1') {
            $this->markTestSkipped('Set TUBECAST_E2E=1 to run live network tests.');
        }
    })
    ->in('E2E');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Helpers — use Tests\Support\Fixtures directly in tests
|--------------------------------------------------------------------------
*/
