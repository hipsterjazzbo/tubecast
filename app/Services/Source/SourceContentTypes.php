<?php

declare(strict_types=1);

namespace App\Services\Source;

use App\Models\Source;

final class SourceContentTypes
{
    public static function label(Source $source): string
    {
        $parts = ['Long-form'];

        if ($source->includeShorts) {
            $parts[] = 'shorts';
        }

        if ($source->includeLive) {
            $parts[] = 'live';
        }

        return implode(' · ', $parts);
    }
}
