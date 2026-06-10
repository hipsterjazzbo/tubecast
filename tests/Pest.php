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
        $dataPath = getenv('DATA_PATH') ?: dirname(__DIR__) . '/data';
        $storedCommands = rtrim($dataPath, '/') . '/stored-commands';

        foreach ([$storedCommands, '/tmp/tubecast-test/downloads', '/tmp/tubecast-test/podcast'] as $directory) {
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }

        foreach (glob($storedCommands . '/*.pending.txt') ?: [] as $path) {
            unlink($path);
        }

        $this->useTestingDatabase();
        $this->database->reset();
    })
    ->in('Feature', 'Ui');

pest()->extend(IntegrationTestCase::class)
    ->beforeEach(function (): void {
        $dataPath = getenv('DATA_PATH') ?: dirname(__DIR__) . '/data';
        $storedCommands = rtrim($dataPath, '/') . '/stored-commands';

        foreach ([$storedCommands, '/tmp/tubecast-test/downloads', '/tmp/tubecast-test/podcast'] as $directory) {
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }

        foreach (glob($storedCommands . '/*.pending.txt') ?: [] as $path) {
            unlink($path);
        }

        $this->useTestingDatabase();
        $this->database->reset();
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
