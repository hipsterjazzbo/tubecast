<?php

declare(strict_types=1);

use App\Services\Source\SourceEpisodeQuery;

describe('SourceEpisodeQuery', function (): void {
    it('builds partial urls with query params', function (): void {
        $query = new SourceEpisodeQuery(sort: SourceEpisodeQuery::SORT_OLDEST, showFiltered: true);

        expect($query->partialUrl(3))->toBe('/sources/3/episodes/partial?sort=oldest&showFiltered=1');
    });
});
