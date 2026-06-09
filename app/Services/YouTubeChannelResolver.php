<?php

declare(strict_types=1);

namespace App\Services;

final class YouTubeChannelResolver
{
    public function __construct(
        private YouTubeRssUrlBuilder $rssUrlBuilder,
        private YouTubeDataApiService $youtubeApi,
        private YouTubeChannelPageScraper $pageScraper,
        private YtDlpService $ytDlp,
    ) {
    }

    public function resolve(string $url): ?string
    {
        $fromUrl = $this->rssUrlBuilder->extractChannelId($url);

        if ($fromUrl !== null) {
            return $fromUrl;
        }

        if ($this->youtubeApi->isConfigured()) {
            $fromApi = $this->youtubeApi->resolveChannelId($url);

            if ($fromApi !== null) {
                return $fromApi;
            }
        }

        $fromPage = $this->pageScraper->resolveChannelId($url);

        if ($fromPage !== null) {
            return $fromPage;
        }

        return $this->ytDlp->resolveChannelId($url);
    }
}
