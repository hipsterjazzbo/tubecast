<?php

declare(strict_types=1);

namespace App\Enums;

enum SourceType: string
{
    case Channel = 'channel';
    case Playlist = 'playlist';
    case Video = 'video';
}
