<?php

declare(strict_types=1);

namespace App\Requests;

use Tempest\Http\IsRequest;
use Tempest\Http\Request;
use Tempest\Validation\Rules;

final class LoginRequest implements Request
{
    use IsRequest;

    #[Rules\IsNotEmptyString]
    public string $username = '';

    #[Rules\IsNotEmptyString]
    public string $password = '';
}
