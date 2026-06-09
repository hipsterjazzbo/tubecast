<?php

declare(strict_types=1);

use Tempest\Database\Config\SQLiteConfig;

use function Tempest\env;
use function Tempest\root_path;

$databasePath = env('DB_DATABASE') ?? 'database/database.sqlite';

if (! str_starts_with($databasePath, '/') && ! str_starts_with($databasePath, ':')) {
    $databasePath = root_path($databasePath);
}

return new SQLiteConfig(path: $databasePath);
