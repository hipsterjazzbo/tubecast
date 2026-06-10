<?php

declare(strict_types=1);

use App\Enums\SourceType;
use App\Models\GlobalSetting;
use App\Repositories\SettingsRepository;
use App\Services\YouTube\YouTubeChannelPageScraper;
use App\Services\YouTube\YouTubeDataApiException;
use App\Services\YouTube\YouTubeDataApiService;
use App\Services\YouTube\YouTubeRssUrlBuilder;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

describe('YouTubeDataApiService', function (): void {
    beforeEach(function (): void {
        GlobalSetting::create(settingKey: 'youtubeApiKey', value: 'test-key');
    });

    it('maps channel uploads into video metadata', function (): void {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'items' => [[
                    'id' => 'UC1234567890123456789012',
                    'snippet' => ['title' => 'Critical Role'],
                    'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU1234567890123456789012']],
                    'statistics' => ['videoCount' => 2],
                ]],
            ], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode([
                'items' => [[
                    'snippet' => ['resourceId' => ['videoId' => 'vid001']],
                    'contentDetails' => ['videoId' => 'vid001'],
                ]],
            ], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode([
                'items' => [[
                    'id' => 'vid001',
                    'snippet' => [
                        'title' => 'Episode One',
                        'description' => 'Desc',
                        'publishedAt' => '2024-01-15T12:00:00Z',
                        'liveBroadcastContent' => 'none',
                    ],
                    'contentDetails' => ['duration' => 'PT1H2M3S'],
                ]],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $service = new YouTubeDataApiService(
            new SettingsRepository(),
            new YouTubeRssUrlBuilder(),
            new YouTubeChannelPageScraper(),
            new Client(['handler' => HandlerStack::create($mock)]),
        );

        $videos = [];
        $count = $service->eachSourceVideo(
            SourceType::Channel,
            'https://www.youtube.com/@CriticalRole',
            function ($video) use (&$videos): void {
                $videos[] = $video;
            },
        );

        expect($count)->toBe(1)
            ->and($videos)->toHaveCount(1)
            ->and($videos[0]->id)->toBe('vid001')
            ->and($videos[0]->title)->toBe('Episode One')
            ->and($videos[0]->duration)->toBe(3723.0)
            ->and($videos[0]->raw['live_status'])->toBe('not_live')
            ->and($videos[0]->raw['published_at'])->toBe('2024-01-15T12:00:00Z');
    });

    it('throws when the API returns an error payload', function (): void {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'error' => ['message' => 'API key invalid'],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $service = new YouTubeDataApiService(
            new SettingsRepository(),
            new YouTubeRssUrlBuilder(),
            new YouTubeChannelPageScraper(),
            new Client(['handler' => HandlerStack::create($mock)]),
        );

        expect(fn () => $service->resolveChannel('https://www.youtube.com/channel/UC1234567890123456789012'))
            ->toThrow(YouTubeDataApiException::class, 'API key invalid');
    });

    it('resolves legacy vanity channel URLs via forHandle', function (): void {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'items' => [[
                    'id' => 'UC1234567890123456789012',
                    'snippet' => ['title' => 'Oculus Imperia'],
                    'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU1234567890123456789012']],
                    'statistics' => ['videoCount' => 1],
                ]],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $service = new YouTubeDataApiService(
            new SettingsRepository(),
            new YouTubeRssUrlBuilder(),
            new YouTubeChannelPageScraper(),
            new Client(['handler' => HandlerStack::create($mock)]),
        );

        $channel = $service->resolveChannel('https://www.youtube.com/oculusimperia');

        expect($channel?->channelId)->toBe('UC1234567890123456789012')
            ->and($channel?->title)->toBe('Oculus Imperia');
    });
});
