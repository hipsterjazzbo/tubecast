<?php

declare(strict_types=1);

use App\Models\MediaProfile;

describe('tubecast:init', function (): void {
    it('bootstraps directories and default profiles with --skip-deps', function (): void {
        expect(MediaProfile::count()->execute())->toBe(0);

        $this->console
            ->call('tubecast:init', ['--skip-deps', '--admin'])
            ->assertSuccess();

        expect(MediaProfile::count()->execute())->toBe(2)
            ->and(is_dir('/tmp/tubecast-test/bin'))->toBeTrue();
    });

    it('rejects --check combined with --skip-deps', function (): void {
        $this->console
            ->call('tubecast:init', ['--check', '--skip-deps'])
            ->assertError();
    });
});
