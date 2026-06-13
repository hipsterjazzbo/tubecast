<?php

declare(strict_types=1);

namespace App\Services\MediaServer;

use App\Enums\MediaServerLibraryType;
use App\Models\MediaItem;
use App\Models\MediaServerLibrary;
use App\Models\Source;
use App\Services\Core\ModelId;
use Tempest\Database\Direction;
use Tempest\DateTime\DateTime;

final class MediaMetadataWriter
{
    public function __construct(
        private TitleSanitizer $sanitizer,
        private MediaServerOutputTemplateResolver $templates,
        private SeasonEpisodeResolver $seasonEpisode,
    ) {
    }

    public function writeForCompletedItem(Source $source, MediaItem $item): void
    {
        $library = $this->libraryFor($source);

        if ($library === null || $library->libraryType !== MediaServerLibraryType::Tv) {
            return;
        }

        if ($item->filePath === null || ! is_file($item->filePath)) {
            return;
        }

        $showTitle = $this->templates->showTitle($source);
        $episode = $this->seasonEpisode->resolve($source, $item);
        $showDir = dirname($item->filePath);

        if (! is_dir($showDir)) {
            return;
        }

        $this->writeTvShowNfo($showDir, $showTitle, $source);
        $this->writeEpisodeNfo($item->filePath, $source, $item, $episode);
    }

    private function libraryFor(Source $source): ?MediaServerLibrary
    {
        if ($source->mediaServerLibraryId === null) {
            return null;
        }

        return MediaServerLibrary::findById($source->mediaServerLibraryId);
    }

    private function writeTvShowNfo(string $showDir, string $showTitle, Source $source): void
    {
        $path = $showDir . '/tvshow.nfo';

        if (is_file($path)) {
            return;
        }

        $premiered = $this->oldestPublishedAt(ModelId::int($source->id));
        $plot = htmlspecialchars($source->title ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($showTitle, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $premieredXml = $premiered !== null
            ? '<premiered>' . htmlspecialchars($premiered->format('Y-m-d'), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</premiered>'
            : '';

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<tvshow>
  <title>{$title}</title>
  <plot>{$plot}</plot>
  {$premieredXml}
</tvshow>
XML;

        file_put_contents($path, $xml);
    }

    private function writeEpisodeNfo(string $videoPath, Source $source, MediaItem $item, int $episode): void
    {
        $base = preg_replace('/\.[^.]+$/', '', $videoPath) ?? $videoPath;
        $path = $base . '.nfo';
        $title = htmlspecialchars($this->templates->episodeTitle($item), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $plot = htmlspecialchars($item->description ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $aired = $item->publishedAt !== null
            ? '<aired>' . htmlspecialchars($item->publishedAt->format('Y-m-d'), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</aired>'
            : '';
        $ytId = htmlspecialchars($item->ytId, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $uniqueIds = '<uniqueid type="youtube" default="true">' . $ytId . '</uniqueid>';

        if ($source->tmdbSeriesId !== null) {
            $uniqueIds .= '<uniqueid type="tmdb">' . $source->tmdbSeriesId . '</uniqueid>';
        }

        if ($source->tvdbSeriesId !== null) {
            $uniqueIds .= '<uniqueid type="tvdb">' . $source->tvdbSeriesId . '</uniqueid>';
        }

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<episodedetails>
  <title>{$title}</title>
  <plot>{$plot}</plot>
  {$aired}
  <season>1</season>
  <episode>{$episode}</episode>
  {$uniqueIds}
</episodedetails>
XML;

        file_put_contents($path, $xml);
    }

    private function oldestPublishedAt(int $sourceId): ?DateTime
    {
        $item = MediaItem::select()
            ->where('sourceId = ? AND publishedAt IS NOT NULL', $sourceId)
            ->orderBy('publishedAt', Direction::ASC)
            ->orderBy('id', Direction::ASC)
            ->first();

        return $item?->publishedAt;
    }
}
