<?php

declare(strict_types=1);

namespace App\Requests;

use Tempest\Http\IsRequest;
use Tempest\Http\Request;
use Tempest\Validation\Rules;

final class StoreSourceRequest implements Request
{
    use IsRequest;
    use SourceFormFields;

    #[Rules\IsNotEmptyString]
    #[Rules\IsUrl]
    public string $url = '';
}
