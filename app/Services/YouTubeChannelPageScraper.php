<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

final class YouTubeChannelPageScraper
{
    public function __construct(
        private Client $http = new Client(),
    ) {
    }

    public function resolveChannelId(string $url): ?string
    {
        try {
            $response = $this->http->get($url, [
                RequestOptions::HEADERS => [
                    'User-Agent' => 'Mozilla/5.0 (compatible; TubeCast/1.0; +https://github.com/tempestphp/tempest)',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ],
                RequestOptions::TIMEOUT => 15,
                RequestOptions::ALLOW_REDIRECTS => true,
            ]);

            $html = (string) $response->getBody();

            if (preg_match('#/channel/(UC[\w-]{22})#', $html, $match)) {
                return $match[1];
            }

            if (preg_match('#"channelId":"(UC[\w-]{22})"#', $html, $match)) {
                return $match[1];
            }

            if (preg_match('#"externalId":"(UC[\w-]{22})"#', $html, $match)) {
                return $match[1];
            }

            if (preg_match('#"browseId":"(UC[\w-]{22})"#', $html, $match)) {
                return $match[1];
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
