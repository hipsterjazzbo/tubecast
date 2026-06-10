<?php

declare(strict_types=1);

namespace App\Database;

use App\Enums\DiscoveredVia;
use App\Enums\EnclosureMode;
use App\Enums\MediaItemStatus;
use App\Enums\SourceType;
use Tempest\Database\MigratesUp;
use Tempest\Database\QueryStatement;
use Tempest\Database\QueryStatements\CompoundStatement;
use Tempest\Database\QueryStatements\CreateTableStatement;
use Tempest\Database\QueryStatements\OnDelete;

final class CreateTubecastSchema implements MigratesUp
{
    public string $name = '2026_06_09_create_tubecast_schema';

    public function up(): QueryStatement
    {
        return new CompoundStatement(
            $this->mediaProfiles(),
            $this->sources(),
            $this->mediaItems(),
            $this->downloadJobs(),
            $this->feeds(),
            $this->globalSettings(),
        );
    }

    private function mediaProfiles(): CreateTableStatement
    {
        return new CreateTableStatement('media_profiles')
            ->primary()
            ->string('name')
            ->boolean('audioOnly', default: false)
            ->string('formatSelector', default: 'bestvideo+bestaudio/best')
            ->string('mergeFormat', nullable: true)
            ->integer('maxHeight', nullable: true)
            ->string('subtitleLanguages', nullable: true)
            ->boolean('sponsorblockRemove', default: false)
            ->integer('podcastBitrateKbps', default: 96)
            ->datetime('createdAt', current: true)
            ->datetime('updatedAt', current: true);
    }

    private function sources(): CreateTableStatement
    {
        return new CreateTableStatement('sources')
            ->primary()
            ->string('url')
            ->enum('type', SourceType::class, default: SourceType::Channel)
            ->string('title', nullable: true)
            ->boolean('includeShorts', default: false)
            ->boolean('includeLive', default: false)
            ->string('youtubeChannelId', nullable: true)
            ->string('youtubeRssUrl', nullable: true)
            ->foreignId('mediaProfileId', 'media_profiles', OnDelete::SET_NULL, nullable: true)
            ->text('filtersJson', nullable: true)
            ->string('outputTemplate', nullable: true)
            ->boolean('saveVideo', default: true)
            ->boolean('saveAudio', default: false)
            ->boolean('enabled', default: true)
            ->datetime('lastFastIndexedAt', nullable: true)
            ->datetime('lastFullIndexedAt', nullable: true)
            ->integer('fastIndexFailures', default: 0)
            ->integer('indexExpectedTotal', nullable: true)
            ->integer('fullIndexProcessedCount', nullable: true)
            ->datetime('createdAt', current: true)
            ->datetime('updatedAt', current: true);
    }

    private function mediaItems(): CreateTableStatement
    {
        return new CreateTableStatement('media_items')
            ->primary()
            ->foreignId('sourceId', 'sources', OnDelete::CASCADE)
            ->string('ytId')
            ->string('title', nullable: true)
            ->text('description', nullable: true)
            ->integer('durationSeconds', nullable: true)
            ->datetime('publishedAt', nullable: true)
            ->enum('status', MediaItemStatus::class, default: MediaItemStatus::Discovered)
            ->enum('discoveredVia', DiscoveredVia::class, default: DiscoveredVia::Rss)
            ->string('filePath', nullable: true)
            ->string('podcastFilePath', nullable: true)
            ->integer('podcastFileSize', nullable: true)
            ->string('podcastMime', nullable: true)
            ->text('metadataJson', nullable: true)
            ->datetime('createdAt', current: true)
            ->datetime('updatedAt', current: true)
            ->unique('sourceId', 'ytId');
    }

    private function downloadJobs(): CreateTableStatement
    {
        return new CreateTableStatement('download_jobs')
            ->primary()
            ->foreignId('mediaItemId', 'media_items', OnDelete::CASCADE)
            ->datetime('startedAt', current: true)
            ->datetime('finishedAt', nullable: true)
            ->integer('exitCode', nullable: true)
            ->text('stderrSnippet', nullable: true);
    }

    private function feeds(): CreateTableStatement
    {
        return new CreateTableStatement('feeds')
            ->primary()
            ->foreignId('sourceId', 'sources', OnDelete::CASCADE, nullable: true)
            ->string('slug')
            ->string('title')
            ->string('token')
            ->integer('maxEpisodes', nullable: true)
            ->enum('enclosureMode', EnclosureMode::class, default: EnclosureMode::Podcast)
            ->boolean('enabled', default: true)
            ->datetime('createdAt', current: true)
            ->datetime('updatedAt', current: true)
            ->unique('slug');
    }

    private function globalSettings(): CreateTableStatement
    {
        return new CreateTableStatement('global_settings')
            ->primary()
            ->string('settingKey')
            ->text('value')
            ->unique('settingKey');
    }
}
