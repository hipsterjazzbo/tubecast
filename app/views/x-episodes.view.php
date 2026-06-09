<section id="episodes-panel" class="space-y-3"
         hx-get="{{ $episodeQuery->partialUrl($source->id) }}"
         hx-trigger="{{ $pollTrigger }}"
         hx-swap="outerHTML">
    <p :if="$episodeRows === [] && ! $indexingProgress->active" class="text-slate-500 text-sm py-8 text-center">No episodes yet. Indexing runs in the background after you add a source.</p>
    <p :if="$episodeRows === [] && $indexingProgress->active" class="text-slate-500 text-sm py-8 text-center">Waiting for first episodes…</p>
    <p :if="$episodeRows === [] && ! $indexingProgress->active && $episodeQuery->showFiltered === false && $stats['filtered'] > 0" class="text-slate-500 text-sm py-8 text-center">No matching episodes in view. Try showing excluded episodes.</p>

    <article :foreach="$episodeRows as $row" class="flex gap-4 p-4 rounded-xl bg-slate-900/50 border hover:border-slate-700 transition {{ $row->filterResult->rowBorderClass() }}">
        <div class="shrink-0 w-36 aspect-video rounded-lg overflow-hidden bg-slate-800">
            <img :src="$row->presentation->thumbnailUrl" alt="" class="w-full h-full object-cover" loading="lazy">
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <h3 class="font-medium text-slate-100 line-clamp-2">{{ $row->episode->title ?? $row->episode->ytId }}</h3>
                <div class="flex flex-wrap gap-1.5 justify-end">
                    <span :if="$row->filterResult->matches === false || ($showFilterBadges && $row->filterResult->matches !== false)"
                          class="shrink-0 text-xs font-medium px-2 py-1 rounded-full ring-1 ring-inset {{ $row->filterResult->badgeClass() }}">{{ $row->filterResult->label() }}</span>
                    <span class="shrink-0 text-xs font-medium px-2 py-1 rounded-full {{ $row->presentation->statusColorClass }}">{{ $row->presentation->statusLabel }}</span>
                </div>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                <span :if="$row->presentation->publishedLabel !== null">{{ $row->presentation->publishedLabel }}</span>
                <span :if="$row->presentation->publishedLabel !== null" class="mx-1">·</span>
                <span>{{ $row->presentation->durationLabel }}</span>
                <span :if="$row->presentation->fileSizeLabel !== null" class="mx-1">·</span>
                <span :if="$row->presentation->fileSizeLabel !== null">{{ $row->presentation->fileSizeLabel }}</span>
            </p>
        </div>
        <div class="shrink-0 self-center flex flex-col gap-2">
            <form :if="$row->showDownloadButton" method="post" action="/sources/{{ $source->id }}/episodes/{{ $row->episode->id }}/download">
                <button type="submit" class="text-xs px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition">Download</button>
            </form>
            <form :if="$row->episode->status->value === 'failed'" method="post" action="/sources/{{ $source->id }}/episodes/{{ $row->episode->id }}/retry">
                <button type="submit" class="text-xs px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition">Retry</button>
            </form>
        </div>
    </article>
</section>
