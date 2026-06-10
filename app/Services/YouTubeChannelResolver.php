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
            try {
                $fromApi = $this->youtubeApi->resolveChannelId($url);
            } catch (YouTubeDataApiException) {
                $fromApi = null;
            }

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
