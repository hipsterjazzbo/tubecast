<?php

declare(strict_types=1);

namespace App\Commands;

use App\Authentication\User;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;
use Tempest\Cryptography\Password\PasswordHasher;

use function Tempest\env;

final readonly class EnsureAdminCommand
{
    use HasConsole;

    public function __construct(
        private PasswordHasher $passwordHasher,
    ) {
    }

    #[ConsoleCommand(
        name: 'tubecast:ensure-admin',
        description: 'Create or update the admin user from ADMIN_USERNAME and ADMIN_PASSWORD',
    )]
    public function __invoke(): ExitCode
    {
        $username = trim((string) env('ADMIN_USERNAME', ''));
        $password = (string) env('ADMIN_PASSWORD', '');

        if ($username === '' || $password === '') {
            $this->console->error('ADMIN_USERNAME and ADMIN_PASSWORD must be set.');

            return ExitCode::FAILURE;
        }

        $user = User::select()
            ->where('username = ?', $username)
            ->first();

        if ($user === null) {
            User::create(
                username: $username,
                password: $password,
            );
            $this->console->info('Admin user created.');

            return ExitCode::SUCCESS;
        }

        if (! $this->passwordHasher->verify($password, $user->password ?? '')) {
            $user->password = $password;
            $user->save();
            $this->console->info('Admin password updated.');
        } else {
            $this->console->info('Admin user already up to date.');
        }

        return ExitCode::SUCCESS;
    }
}
