<?php

declare(strict_types=1);

namespace App\Enums;

enum MediaServerLibraryType: string
{
    case Movie = 'movie';
    case Tv = 'tv';
    case Music = 'music';
    case Other = 'other';
}
