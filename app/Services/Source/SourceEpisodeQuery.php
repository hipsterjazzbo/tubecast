<?php

declare(strict_types=1);

namespace App\Services\Source;

use App\Services\Core\ModelId;
use Tempest\Database\Direction;
use Tempest\Database\PrimaryKey;
use Tempest\Http\Request;

final readonly class SourceEpisodeQuery
{
    public const string SORT_NEWEST = 'newest';
    public const string SORT_OLDEST = 'oldest';
    public const string SORT_INDEXED = 'indexed';

    public function __construct(
        public string $sort = self::SORT_NEWEST,
        public bool $showFiltered = false,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $sort = (string) $request->get('sort', self::SORT_NEWEST);

        if (! in_array($sort, [self::SORT_NEWEST, self::SORT_OLDEST, self::SORT_INDEXED], true)) {
            $sort = self::SORT_NEWEST;
        }

        return new self(
            sort: $sort,
            showFiltered: $request->get('showFiltered') === '1' || $request->get('showFiltered') === 'true',
        );
    }

    public function partialUrl(PrimaryKey|int $sourceId): string
    {
        $id = ModelId::int($sourceId);
        $query = http_build_query([
            'sort' => $this->sort,
            'showFiltered' => $this->showFiltered ? '1' : '0',
        ]);

        return '/sources/' . $id . '/episodes/partial?' . $query;
    }

    /** @return list<array{0: string, 1: Direction}> */
    public function orderBy(): array
    {
        return match ($this->sort) {
            self::SORT_OLDEST => [
                ['publishedAt', Direction::ASC],
                ['id', Direction::ASC],
            ],
            self::SORT_INDEXED => [
                ['id', Direction::DESC],
            ],
            default => [
                ['publishedAt', Direction::DESC],
                ['id', Direction::DESC],
            ],
        };
    }

    public function sortLabel(): string
    {
        return match ($this->sort) {
            self::SORT_OLDEST => 'Oldest first',
            self::SORT_INDEXED => 'Last indexed',
            default => 'Newest first',
        };
    }
}
