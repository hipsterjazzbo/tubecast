<?php

declare(strict_types=1);

namespace App\Authentication;

use Tempest\Auth\Authentication\Authenticatable;
use Tempest\Database\Hashed;
use Tempest\Database\IsDatabaseModel;

final class User implements Authenticatable
{
    use IsDatabaseModel;

    public function __construct(
        public string $username,
        #[Hashed]
        #[\SensitiveParameter]
        public ?string $password,
    ) {
    }
}
