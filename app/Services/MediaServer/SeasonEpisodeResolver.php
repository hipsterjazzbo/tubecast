<?php

declare(strict_types=1);

namespace App\Services\MediaServer;

use App\Models\MediaItem;
use App\Models\Source;
use App\Services\Core\ModelId;
use Tempest\Database\Direction;

final class SeasonEpisodeResolver
{
    public function resolve(Source $source, MediaItem $item): int
    {
        if ($item->seasonEpisode !== null) {
            return $item->seasonEpisode;
        }

        $items = MediaItem::select()
            ->where('sourceId = ?', ModelId::int($source->id))
            ->orderBy('publishedAt', Direction::ASC)
            ->orderBy('id', Direction::ASC)
            ->all();

        $rank = 1;
        $itemId = ModelId::int($item->id);

        foreach ($items as $candidate) {
            if (ModelId::int($candidate->id) === $itemId) {
                $item->seasonEpisode = $rank;
                $item->save();

                return $rank;
            }

            $rank++;
        }

        $item->seasonEpisode = $rank;
        $item->save();

        return $rank;
    }
}
