<?php

declare(strict_types=1);

namespace App\Enums;

enum DiscoveredVia: string
{
    case Rss = 'rss';
    case YtDlp = 'ytdlp';
    case YouTubeApi = 'youtube_api';
    case Manual = 'manual';
}
