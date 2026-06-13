<?php

declare(strict_types=1);

namespace App\Services\MediaServer;

use App\Config\TubecastConfig;
use App\Enums\MediaServerLibraryType;
use App\Models\MediaItem;
use App\Models\MediaServerLibrary;
use App\Models\Source;
use App\Services\Source\SourceMetadataService;

final class MediaServerOutputTemplateResolver
{
    public function __construct(
        private TubecastConfig $config,
        private TitleSanitizer $sanitizer,
        private SeasonEpisodeResolver $seasonEpisode,
        private SourceMetadataService $metadata,
    ) {
    }

    public function resolveVideoTemplate(Source $source, MediaItem $item): string
    {
        if ($source->outputTemplate !== null && $source->outputTemplate !== '') {
            return $source->outputTemplate;
        }

        $library = $this->libraryFor($source);

        if ($library === null) {
            return '%(uploader)s/%(title)s [%(id)s].%(ext)s';
        }

        return match ($library->libraryType) {
            MediaServerLibraryType::Tv => $this->tvTemplate($source, $item),
            MediaServerLibraryType::Movie => $this->movieTemplate($source, $item),
            default => '%(uploader)s/%(title)s [%(id)s].%(ext)s',
        };
    }

    public function showTitle(Source $source): string
    {
        $title = $source->title;

        if ($title === null || trim($title) === '') {
            $this->metadata->ensureTitle($source, allowYtDlp: false);
            $title = $source->title;
        }

        return $this->sanitizer->sanitize($title ?? 'Show');
    }

    public function episodeTitle(MediaItem $item): string
    {
        return $this->sanitizer->sanitize($item->title ?? $item->ytId);
    }

    private function libraryFor(Source $source): ?MediaServerLibrary
    {
        if ($source->mediaServerLibraryId === null) {
            return null;
        }

        $library = MediaServerLibrary::findById($source->mediaServerLibraryId);

        if ($library === null || ! $library->enabled) {
            return null;
        }

        return $library;
    }

    private function tvTemplate(Source $source, MediaItem $item): string
    {
        $show = $this->showTitle($source);
        $episode = $this->seasonEpisode->resolve($source, $item);
        $episodeTitle = $this->episodeTitle($item);
        $root = rtrim($this->config->videoPath, '/');

        return sprintf(
            '%s/%s/Season 01/%s - s01e%02d - %s [%%(id)s].%%(ext)s',
            $root,
            $show,
            $show,
            $episode,
            $episodeTitle,
        );
    }

    private function movieTemplate(Source $source, MediaItem $item): string
    {
        $title = $this->episodeTitle($item);
        $year = $this->yearFromItem($item);
        $root = rtrim($this->config->videoPath, '/');
        $folder = $year !== null ? "{$title} ({$year})" : $title;

        return sprintf(
            '%s/%s/%s.%%(ext)s',
            $root,
            $folder,
            $folder,
        );
    }

    private function yearFromItem(MediaItem $item): ?int
    {
        if ($item->publishedAt === null) {
            return null;
        }

        return (int) $item->publishedAt->getYear();
    }
}
