<?php

declare(strict_types=1);

namespace App\Database;

use App\Enums\MediaServerLibraryType;
use App\Enums\MediaServerType;
use App\Enums\MetadataMode;
use Tempest\Database\MigratesUp;
use Tempest\Database\QueryStatement;
use Tempest\Database\QueryStatements\AlterTableStatement;
use Tempest\Database\QueryStatements\BooleanStatement;
use Tempest\Database\QueryStatements\CompoundStatement;
use Tempest\Database\QueryStatements\CreateTableStatement;
use Tempest\Database\QueryStatements\EnumStatement;
use Tempest\Database\QueryStatements\IntegerStatement;
use Tempest\Database\QueryStatements\OnDelete;
use Tempest\Database\QueryStatements\TextStatement;

final class AddMediaServerSchema implements MigratesUp
{
    public string $name = '2026_06_13_add_media_server_schema';

    public function up(): QueryStatement
    {
        return new CompoundStatement(
            $this->mediaServers(),
            $this->mediaServerLibraries(),
            ...$this->extendSources(),
            ...$this->extendMediaItems(),
        );
    }

    private function mediaServers(): CreateTableStatement
    {
        return new CreateTableStatement('media_servers')
            ->primary()
            ->string('name')
            ->enum('type', MediaServerType::class)
            ->string('baseUrl')
            ->string('apiToken')
            ->string('tubecastVideoRoot')
            ->string('tubecastAudioRoot')
            ->boolean('enabled', default: true)
            ->datetime('lastSyncedAt', nullable: true)
            ->text('lastSyncError', nullable: true)
            ->datetime('createdAt', current: true)
            ->datetime('updatedAt', current: true);
    }

    private function mediaServerLibraries(): CreateTableStatement
    {
        return new CreateTableStatement('media_server_libraries')
            ->primary()
            ->foreignId('mediaServerId', 'media_servers', OnDelete::CASCADE)
            ->string('externalId')
            ->string('name')
            ->enum('libraryType', MediaServerLibraryType::class, default: MediaServerLibraryType::Other)
            ->string('remoteRoot', nullable: true)
            ->boolean('enabled', default: true)
            ->datetime('createdAt', current: true)
            ->datetime('updatedAt', current: true)
            ->unique('mediaServerId', 'externalId');
    }

    /** @return list<AlterTableStatement> */
    private function extendSources(): array
    {
        return [
            (new AlterTableStatement('sources'))
                ->add(new BooleanStatement('notifyMediaServer', default: false)),
            (new AlterTableStatement('sources'))
                ->add(new IntegerStatement('mediaServerLibraryId', nullable: true)),
            (new AlterTableStatement('sources'))
                ->add(new EnumStatement('metadataMode', MetadataMode::class, default: MetadataMode::Local)),
            (new AlterTableStatement('sources'))
                ->add(new IntegerStatement('tmdbSeriesId', nullable: true)),
            (new AlterTableStatement('sources'))
                ->add(new IntegerStatement('tvdbSeriesId', nullable: true)),
        ];
    }

    /** @return list<AlterTableStatement> */
    private function extendMediaItems(): array
    {
        return [
            (new AlterTableStatement('media_items'))
                ->add(new IntegerStatement('seasonEpisode', nullable: true)),
        ];
    }
}
