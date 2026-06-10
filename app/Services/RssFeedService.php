<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MediaItemStatus;
use App\Enums\RssFeedFormat;
use App\Models\Feed;
use App\Models\MediaItem;
use Tempest\Database\Direction;
use Tempest\DateTime\FormatPattern;

use function Tempest\env;

final readonly class RssFeedService
{
    public function buildAudio(Feed $feed): string
    {
        return $this->build($feed, RssFeedFormat::Audio);
    }

    public function buildVideo(Feed $feed): string
    {
        return $this->build($feed, RssFeedFormat::Video);
    }

    private function build(Feed $feed, RssFeedFormat $format): string
    {
        $query = MediaItem::select()
            ->where('status = ?', MediaItemStatus::Completed->value)
            ->orderBy('publishedAt', Direction::DESC)
            ->limit($feed->maxEpisodes ?? 100);

        $query = match ($format) {
            RssFeedFormat::Video => $query->where('filePath IS NOT NULL'),
            RssFeedFormat::Audio => $query->where('podcastFilePath IS NOT NULL'),
        };

        if ($feed->sourceId !== null) {
            $query = $query->where('sourceId = ?', $feed->sourceId);
        }

        $items = $query->all();

        $baseUri = rtrim(env('BASE_URI', 'http://localhost:8000'), '/');
        $feedUrl = match ($format) {
            RssFeedFormat::Video => $baseUri . '/feeds/' . $feed->slug . '/video.xml?token=' . urlencode($feed->token),
            RssFeedFormat::Audio => $baseUri . '/feeds/' . $feed->slug . '/audio.xml?token=' . urlencode($feed->token),
        };

        $rssRoot = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"/>';
        $xml = new \SimpleXMLElement($rssRoot);
        $channel = $xml->addChild('channel');
        $channel->addChild('title', htmlspecialchars($feed->title));
        $channel->addChild('link', $feedUrl);
        $channel->addChild('description', htmlspecialchars($feed->title));
        $channel->addChild('language', 'en-us');

        foreach ($items as $item) {
            $entry = $channel->addChild('item');
            $entry->addChild('title', htmlspecialchars($item->title ?? $item->ytId));
            $entry->addChild('guid', $item->ytId)->addAttribute('isPermaLink', 'false');

            if ($item->publishedAt !== null) {
                $entry->addChild('pubDate', $item->publishedAt->format(FormatPattern::RFC2822));
            }

            if ($item->description !== null) {
                $entry->addChild('description', htmlspecialchars($item->description));
            }

            [$url, $length, $mime] = match ($format) {
                RssFeedFormat::Video => [
                    $baseUri . '/media/' . $feed->token . '/' . $item->ytId . '/file',
                    $item->filePath !== null && is_file($item->filePath) ? (filesize($item->filePath) ?: 0) : 0,
                    'video/mp4',
                ],
                RssFeedFormat::Audio => [
                    $baseUri . '/media/' . $feed->token . '/' . $item->ytId . '/audio.m4a',
                    $item->podcastFileSize ?? 0,
                    $item->podcastMime ?? 'audio/mp4',
                ],
            };

            $enclosure = $entry->addChild('enclosure');
            $enclosure->addAttribute('url', $url);
            $enclosure->addAttribute('length', (string) $length);
            $enclosure->addAttribute('type', $mime);
        }

        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;

        return $dom->saveXML() ?: '';
    }
}
