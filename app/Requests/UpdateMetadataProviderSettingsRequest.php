<?php

declare(strict_types=1);

namespace App\Requests;

use Tempest\Http\IsRequest;
use Tempest\Http\Request;
use Tempest\Validation\SkipValidation;

final class UpdateMetadataProviderSettingsRequest implements Request
{
    use IsRequest;

    #[SkipValidation]
    public mixed $tmdbApiKey = null;

    #[SkipValidation]
    public mixed $tvdbApiKey = null;
}
