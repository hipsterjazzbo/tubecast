<?php

declare(strict_types=1);

namespace App\Models;

use Tempest\Database\IsDatabaseModel;

final class GlobalSetting
{
    use IsDatabaseModel;

    public string $settingKey;
    public string $value;
}
