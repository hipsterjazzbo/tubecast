<?php

declare(strict_types=1);

namespace App\Services\YouTube;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

final class YouTubeRssService
{
    public function __construct(
        private Client $http = new Client(),
    ) {
    }

    public function fetchFeedTitle(string $rssUrl): ?string
    {
        try {
            $xml = $this->loadFeed($rssUrl);
        } catch (\Throwable) {
            return null;
        }

        $title = trim((string) ($xml->title ?? ''));

        if ($title === '') {
            return null;
        }

        foreach ([' - YouTube', ' - Videos'] as $suffix) {
            if (str_ends_with($title, $suffix)) {
                $title = substr($title, 0, -strlen($suffix));
            }
        }

        return trim($title) !== '' ? trim($title) : null;
    }

    /** @return list<YouTubeRssEntry> */
    public function fetchEntries(string $rssUrl): array
    {
        $xml = $this->loadFeed($rssUrl);

        $xml->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
        $xml->registerXPathNamespace('yt', 'http://www.youtube.com/xml/schemas/2015');

        $entries = [];

        foreach ($xml->entry as $entry) {
            $namespaces = $entry->getNameSpaces(true);
            $yt = $entry->children($namespaces['yt'] ?? 'http://www.youtube.com/xml/schemas/2015');
            $videoId = (string) ($yt->videoId ?? '');

            if ($videoId === '') {
                continue;
            }

            $published = (string) ($entry->published ?? $entry->updated ?? '');

            $entries[] = new YouTubeRssEntry(
                videoId: $videoId,
                title: (string) $entry->title,
                publishedAt: $published,
                url: 'https://www.youtube.com/watch?v=' . $videoId,
            );
        }

        return $entries;
    }

    private function loadFeed(string $rssUrl): \SimpleXMLElement
    {
        $response = $this->http->get($rssUrl, [
            RequestOptions::HEADERS => [
                'User-Agent' => 'TubeCast/1.0 (RSS indexer)',
            ],
            RequestOptions::TIMEOUT => 30,
        ]);

        $xml = simplexml_load_string((string) $response->getBody());

        if ($xml === false) {
            throw new \RuntimeException('Failed to parse YouTube RSS feed.');
        }

        return $xml;
    }
}
