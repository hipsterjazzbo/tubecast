<x-base title="Sources">
    <main class="mx-auto max-w-5xl px-6 py-8">
        <header class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white">Sources</h1>
                <p class="mt-1 text-sm text-slate-500">YouTube channels and playlists you follow</p>
            </div>
            <div class="flex items-center gap-6">
                <x-nav />
                <a href="/sources/create" class="text-sm px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition">Add source</a>
            </div>
        </header>

        <p :if="$sourceRows === []" class="text-center text-slate-500 py-16 rounded-xl border border-dashed border-slate-800">
            No sources yet. <a href="/sources/create" class="text-indigo-400 hover:text-indigo-300">Add your first channel</a>.
        </p>

        <div class="grid gap-4 sm:grid-cols-2">
            <div :foreach="$sourceRows as $row"
                 class="rounded-xl border border-slate-800 bg-slate-900/40 p-5 hover:border-slate-600 hover:bg-slate-900/70 transition">
                <div class="flex items-start justify-between gap-3">
                    <a href="/sources/{{ $row->source->id }}" class="min-w-0 flex-1">
                        <h2 class="font-medium text-slate-100 truncate hover:text-white transition">{{ $row->source->title ?? $row->source->url }}</h2>
                        <p class="mt-1 text-xs text-slate-500 truncate">{{ $row->source->url }}</p>
                    </a>
                    <span class="shrink-0 text-xs px-2 py-1 rounded-full bg-slate-800 text-slate-400">{{ $row->source->type->value }}</span>
                </div>
                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                    <span class="px-2 py-1 rounded-full bg-slate-800 text-slate-300">{{ $row->stats['completed'] }} / {{ $row->stats['total'] }} ready</span>
                    <span :if="$row->stats['downloading'] > 0" class="px-2 py-1 rounded-full bg-sky-500/15 text-sky-300">{{ $row->stats['downloading'] }} downloading</span>
                    <span class="px-2 py-1 rounded-full {{ $row->saveLabelMuted ? 'bg-slate-800 text-slate-400' : 'bg-emerald-500/15 text-emerald-300' }}">{{ $row->saveLabel }}</span>
                    <span :if="$row->source->youtubeRssUrl !== null" class="px-2 py-1 rounded-full bg-slate-800 text-slate-500">RSS</span>
                </div>
                <div class="mt-4 flex items-center gap-2 border-t border-slate-800 pt-4">
                    <a href="/sources/{{ $row->source->id }}/edit"
                       class="text-xs px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition">Edit</a>
                    <form method="post" action="/sources/{{ $row->source->id }}/delete"
                          onsubmit="return confirm('Permanently delete this source, all {{ $row->stats['total'] }} episodes, and any downloaded files? This cannot be undone.')">
                        <button type="submit" class="text-xs px-3 py-1.5 rounded-lg bg-red-950/50 hover:bg-red-900/50 text-red-300 transition">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</x-base>
