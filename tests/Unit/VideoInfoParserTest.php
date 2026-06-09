<?php

declare(strict_types=1);

use App\Support\VideoInfoParser;

describe('VideoInfoParser', function (): void {
    it('parses yt-dlp json lines without tempest bootstrap', function (): void {
        $json = json_encode([
            'id' => 'abc123',
            'title' => 'Campaign Episode',
            'duration' => 7200,
            'description' => 'A long episode',
            'webpage_url' => 'https://www.youtube.com/watch?v=abc123',
            'live_status' => 'not_live',
        ], JSON_THROW_ON_ERROR);

        $info = VideoInfoParser::fromJsonLine($json);

        expect($info->id)->toBe('abc123')
            ->and($info->title)->toBe('Campaign Episode')
            ->and($info->duration)->toBe(7200.0)
            ->and($info->raw['live_status'])->toBe('not_live');
    });
});
