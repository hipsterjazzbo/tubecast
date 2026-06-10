<?php

declare(strict_types=1);

namespace App\Requests;

use Tempest\Http\IsRequest;
use Tempest\Http\Request;

final class UpdateYtDlpSettingsRequest implements Request
{
    use IsRequest;

    public string $ytDlpCookiesFile = '';

    public string $ytDlpProxy = '';
}
