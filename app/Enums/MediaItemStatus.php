<?php

declare(strict_types=1);

namespace App\Enums;

enum MediaItemStatus: string
{
    case Discovered = 'discovered';
    case Pending = 'pending';
    case Indexed = 'indexed';
    case Downloading = 'downloading';
    case Completed = 'completed';
    case Failed = 'failed';
    case Throttled = 'throttled';
    case Filtered = 'filtered';
}
