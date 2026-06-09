<?php

declare(strict_types=1);

namespace App\Support;

final readonly class EpisodeFilterResult
{
    public function __construct(
        public ?bool $matches,
        public ?string $rejectReason,
    ) {
    }

    public function label(): string
    {
        if ($this->matches === true) {
            return 'Matches filter';
        }

        if ($this->matches === null) {
            return 'Filter pending';
        }

        return 'Excluded: ' . ($this->rejectReason ?? 'Filter');
    }

    public function badgeClass(): string
    {
        return match ($this->matches) {
            true => 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/30',
            false => 'bg-slate-600/20 text-slate-400 ring-slate-500/30',
            null => 'bg-slate-800 text-slate-500 ring-slate-700/50',
        };
    }

    public function rowBorderClass(): string
    {
        return match ($this->matches) {
            true => 'border-emerald-900/40',
            false => 'border-slate-800/80 opacity-80',
            null => 'border-dashed border-slate-700',
        };
    }
}
