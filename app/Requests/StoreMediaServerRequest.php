<?php

declare(strict_types=1);

namespace App\Requests;

use App\Enums\MediaServerType;
use Tempest\Http\IsRequest;
use Tempest\Http\Request;
use Tempest\Validation\Rules;

final class StoreMediaServerRequest implements Request
{
    use IsRequest;

    #[Rules\IsNotEmptyString]
    public string $name = "";

    #[Rules\IsEnum(MediaServerType::class)]
    public MediaServerType $type = MediaServerType::Plex;

    #[Rules\IsNotEmptyString]
    #[Rules\IsUrl]
    public string $baseUrl = "";

    #[Rules\IsNotEmptyString]
    public string $apiToken = "";

    #[Rules\IsNotEmptyString]
    public string $tubecastVideoRoot = "";

    #[Rules\IsNotEmptyString]
    public string $tubecastAudioRoot = "";

    public bool $enabled = true;
}
