<?php

declare(strict_types=1);

namespace App\Requests;

use Tempest\Http\IsRequest;
use Tempest\Http\Request;

final class UpdateSourceSettingsRequest implements Request
{
    use IsRequest;
    use SourceFormFields;
}
