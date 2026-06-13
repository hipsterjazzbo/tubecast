<?php

declare(strict_types=1);

namespace App\Enums;

enum MetadataMode: string
{
    case Local = 'local';
    case Tmdb = 'tmdb';
    case Tvdb = 'tvdb';
}
