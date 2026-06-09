<?php

declare(strict_types=1);

namespace App\Enums;

enum EnclosureMode: string
{
    case Podcast = 'podcast';
    case Archive = 'archive';
    case Both = 'both';
}
