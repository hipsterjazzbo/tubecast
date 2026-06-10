<div id="source-stats" class="space-y-3"
     hx-get="/sources/{{ $source->id }}/stats/partial"
     hx-trigger="{{ $pollTrigger }}"
     hx-swap="outerHTML">
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex flex-wrap gap-2 text-sm flex-1 min-w-0">
            <span class="px-3 py-1 rounded-full bg-slate-800 text-slate-400">Filter: {{ $contentFilterLabel }}</span>
            <span class="px-3 py-1 rounded-full bg-slate-800 text-slate-300">{{ $stats['completed'] }} / {{ $stats['total'] }} ready</span>
            <span :if="$stats['downloading'] > 0" class="px-3 py-1 rounded-full bg-blue-500/20 text-blue-300">{{ $stats['downloading'] }} downloading</span>
            <span :if="$stats['pending'] > 0" class="px-3 py-1 rounded-full bg-amber-500/20 text-amber-200">{{ $stats['pending'] }} queued</span>
            <span :if="$stats['filtered'] > 0" class="px-3 py-1 rounded-full bg-slate-700/80 text-slate-400">{{ $stats['filtered'] }} excluded</span>
            <span :if="$stats['failed'] > 0" class="px-3 py-1 rounded-full bg-red-500/20 text-red-300">{{ $stats['failed'] }} failed</span>
        </div>

        <details id="source-activity" :if="$indexingProgress->active || $sourceProgress->active" class="relative shrink-0 group">
            <summary class="list-none cursor-pointer text-sm px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-900/80 text-slate-200 hover:bg-slate-800 transition inline-flex items-center gap-2 [&::-webkit-details-marker]:hidden">
                <svg class="h-4 w-4 text-indigo-400 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Activity</span>
                <svg class="h-3.5 w-3.5 opacity-60 group-open:rotate-180 transition-transform" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/>
                </svg>
            </summary>
            <div class="absolute right-0 z-20 mt-2 w-80 rounded-xl border border-slate-700 bg-slate-900 p-4 shadow-xl space-y-4">
                <div :if="$indexingProgress->active" class="space-y-2">
                    <div class="flex items-center justify-between text-xs gap-3">
                        <span class="text-indigo-300 font-medium shrink-0">Indexing</span>
                        <span class="text-slate-400 truncate">{{ $indexingProgress->label() }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs text-slate-500" :if="$indexingProgress->indexPercent !== null">
                        <span>Index progress</span>
                        <span>{{ $indexingProgress->indexPercent }}%</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-slate-800 overflow-hidden relative">
                        <div class="h-full rounded-full {{ $indexingProgress->barClass }}"
                             :style="$indexingProgress->widthStyle()"></div>
                    </div>
                </div>

                <div :if="$sourceProgress->active && ($stats['downloading'] > 0 || $stats['pending'] > 0)" class="space-y-2">
                    <div class="flex items-center justify-between text-xs text-slate-500">
                        <span>Downloads</span>
                        <span>{{ $sourceProgress->percent }}%</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-slate-800 overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-sky-400 transition-all duration-500 ease-out {{ $sourceProgress->barClass }}"
                             :style="$sourceProgress->widthStyle()"></div>
                    </div>
                </div>
            </div>
        </details>
    </div>
</div>
