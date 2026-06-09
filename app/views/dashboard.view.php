<x-base title="TubeCast">
    <main class="mx-auto max-w-5xl px-6 py-8">
        <header class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white">Dashboard</h1>
                <p class="mt-1 text-sm text-slate-500">Overview of your TubeCast library</p>
            </div>
            <x-nav />
        </header>

        <div class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Sources</p>
                <p class="mt-1 text-2xl font-semibold text-white">{{ $sourceCount }}</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Episodes</p>
                <p class="mt-1 text-2xl font-semibold text-white">{{ $mediaCount }}</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Completed</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-400">{{ $completedCount }}</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Failed</p>
                <p class="mt-1 text-2xl font-semibold text-rose-400">{{ $failedCount }}</p>
            </div>
        </div>

        <section class="rounded-xl border border-slate-800 bg-slate-900/30 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-medium text-slate-300 uppercase tracking-wide">Recent episodes</h2>
                <a href="/sources" class="text-xs text-indigo-400 hover:text-indigo-300">View sources →</a>
            </div>
            <ul class="divide-y divide-slate-800">
                <li :foreach="$recentRows as $row" class="py-3 flex items-center gap-4">
                    <div class="shrink-0 w-20 aspect-video rounded overflow-hidden bg-slate-800">
                        <img src="{{ $row->thumbnailUrl }}" alt="" class="w-full h-full object-cover" loading="lazy">
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-slate-200 truncate">{{ $row->title }}</p>
                        <p class="text-xs text-slate-500">
                            <span class="{{ $row->statusColorClass }} px-1.5 py-0.5 rounded">{{ $row->statusLabel }}</span>
                            <span class="mx-1">·</span>
                            <span>{{ $row->durationLabel }}</span>
                        </p>
                    </div>
                    <a href="/sources/{{ $row->sourceId }}" class="text-xs text-slate-500 hover:text-slate-300 shrink-0">Source</a>
                </li>
            </ul>
            <p :if="$recentRows === []" class="text-sm text-slate-500 py-6 text-center">No episodes indexed yet.</p>
        </section>
    </main>
</x-base>
