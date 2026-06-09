<?php

declare(strict_types=1);

namespace App\Support;

use Tempest\Database\PrimaryKey;

final class ModelId
{
    public static function int(PrimaryKey|int $id): int
    {
        return $id instanceof PrimaryKey ? $id->value : $id;
    }
}
