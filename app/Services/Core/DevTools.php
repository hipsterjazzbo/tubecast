<?php

declare(strict_types=1);

namespace App\Services\Core;

use Tempest\Core\Environment;

final class DevTools
{
    public static function enabled(): bool
    {
        return ! Environment::guessFromEnvironment()->requiresCaution();
    }
}
