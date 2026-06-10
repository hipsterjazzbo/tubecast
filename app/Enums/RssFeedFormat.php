<?php

declare(strict_types=1);

namespace App\Enums;

enum RssFeedFormat: string
{
    case Audio = 'audio';
    case Video = 'video';
}
