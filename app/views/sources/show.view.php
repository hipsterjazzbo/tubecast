<x-base :title="$source->title ?? 'Source'">
    <main class="mx-auto max-w-4xl px-6 py-8">
        <header class="mb-6">
            <div class="flex items-center justify-between gap-4 mb-3">
                <a href="/sources" class="text-sm text-slate-500 hover:text-slate-300 transition">← Sources</a>
                <x-nav />
            </div>

            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
                <div class="min-w-0 flex-1">
                    <h1 class="text-2xl font-semibold text-white truncate">{{ $source->title ?? $source->url }}</h1>
                    <p class="mt-1 text-sm text-slate-500 truncate">{{ $source->url }}</p>
                </div>

                <div class="flex items-center gap-2 flex-wrap shrink-0">
                    <a href="/sources/{{ $source->id }}/edit"
                       class="text-sm px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 transition"
                       title="Edit source settings and filters">Edit</a>

                    <div class="relative" id="feed-menu" :if="$feed !== null && ($audioFeedUrl !== null || $videoFeedUrl !== null)">
                        <button type="button" id="feed-menu-btn"
                                class="text-sm px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 transition inline-flex items-center gap-1.5"
                                title="Copy RSS feed URLs for podcast apps">
                            RSS
                            <svg class="h-3.5 w-3.5 opacity-60" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <div id="feed-menu-panel"
                             class="hidden absolute right-0 z-20 mt-1 min-w-[12rem] rounded-lg border border-slate-700 bg-slate-900 py-1 shadow-xl">
                            <button type="button" :if="$audioFeedUrl !== null"
                                    class="feed-copy w-full text-left px-3 py-2 text-sm text-slate-200 hover:bg-slate-800 transition"
                                    data-copy="{{ $audioFeedUrl }}"
                                    title="Copy audio podcast feed URL (M4A enclosures)">
                                Audio feed
                            </button>
                            <button type="button" :if="$videoFeedUrl !== null"
                                    class="feed-copy w-full text-left px-3 py-2 text-sm text-slate-200 hover:bg-slate-800 transition"
                                    data-copy="{{ $videoFeedUrl }}"
                                    title="Copy video RSS feed URL (MP4 enclosures)">
                                Video feed
                            </button>
                        </div>
                        <span id="feed-copy-toast"
                              class="hidden absolute right-0 -bottom-8 text-xs text-emerald-400 whitespace-nowrap">Copied</span>
                    </div>

                    <form method="post" action="/sources/{{ $source->id }}/index">
                        <button type="submit" class="text-sm px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition"
                                title="Run a full index now">Index now</button>
                    </form>
                    <form method="post" action="/sources/{{ $source->id }}/delete"
                          onsubmit="return confirm('Permanently delete this source, all {{ $stats['total'] }} episodes, and any downloaded files? This cannot be undone.')">
                        <button type="submit" class="text-sm px-3 py-2 rounded-lg bg-red-950/50 hover:bg-red-900/50 text-red-300 transition"
                                title="Remove this source, its episodes, and downloaded files">Delete</button>
                    </form>
                </div>
            </div>
        </header>

        <div class="space-y-8">
            <x-source-stats :source="$source" :stats="$stats" :contentFilterLabel="$contentFilterLabel" :indexingProgress="$indexingProgress" :pollTrigger="$pollTrigger" :sourceProgress="$sourceProgress" :showFilterBadges="$showFilterBadges" />

            <section id="episodes">
                <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
                    <form id="episode-filters"
                          class="flex flex-wrap items-center gap-3"
                          hx-get="{{ $episodeQuery->partialUrl($source->id) }}"
                          hx-target="#episodes-panel"
                          hx-trigger="change"
                          hx-swap="outerHTML">
                        <h2 class="text-sm font-medium text-slate-300 uppercase tracking-wide mr-1">Episodes</h2>

                        <label class="text-xs text-slate-500 flex items-center gap-1.5">
                            <span>Sort</span>
                            <select name="sort"
                                    class="text-sm rounded-lg border border-slate-700 bg-slate-900 px-2 py-1.5 text-slate-200 focus:border-indigo-500 focus:outline-none">
                                <option value="newest" :selected="$episodeQuery->sort === 'newest'">Newest first</option>
                                <option value="oldest" :selected="$episodeQuery->sort === 'oldest'">Oldest first</option>
                                <option value="indexed" :selected="$episodeQuery->sort === 'indexed'">Last indexed</option>
                            </select>
                        </label>

                        <label class="text-xs text-slate-400 flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" name="showFiltered" value="1"
                                   class="rounded border-slate-600 bg-slate-900 text-indigo-500 focus:ring-indigo-500/40"
                                   :checked="$episodeQuery->showFiltered">
                            Show excluded
                        </label>
                    </form>

                    <form :if="$manualDownload && $canDownload && $pendingManualCount > 0" method="post" action="/sources/{{ $source->id }}/episodes/download-all">
                        <button type="submit" class="text-xs px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition">
                            Download all matching ({{ $pendingManualCount }})
                        </button>
                    </form>
                </div>
                <x-episodes :source="$source" :episodeRows="$episodeRows" :pollTrigger="$pollTrigger" :manualDownload="$manualDownload" :canDownload="$canDownload" :indexingProgress="$indexingProgress" :episodeQuery="$episodeQuery" :showFilterBadges="$showFilterBadges" :stats="$stats" />
            </section>
        </div>
    </main>

    <x-slot name="scripts">
        <script>
            (function () {
                let activityOpen = false;

                document.addEventListener('toggle', function (e) {
                    if (e.target.id === 'source-activity') {
                        activityOpen = e.target.open;
                    }
                }, true);

                document.body.addEventListener('htmx:afterSwap', function (e) {
                    if (e.detail.target?.id !== 'source-stats') {
                        return;
                    }

                    const details = document.getElementById('source-activity');
                    if (!details) {
                        activityOpen = false;
                        return;
                    }

                    if (activityOpen) {
                        details.open = true;
                    }
                });

                const menu = document.getElementById('feed-menu');
                if (!menu) return;

                const btn = document.getElementById('feed-menu-btn');
                const panel = document.getElementById('feed-menu-panel');
                const toast = document.getElementById('feed-copy-toast');
                let toastTimer;

                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    panel.classList.toggle('hidden');
                });

                document.addEventListener('click', function () {
                    panel.classList.add('hidden');
                });

                menu.querySelectorAll('.feed-copy').forEach(function (item) {
                    item.addEventListener('click', async function (e) {
                        e.stopPropagation();
                        const url = item.dataset.copy;
                        try {
                            await navigator.clipboard.writeText(url);
                        } catch {
                            const input = document.createElement('input');
                            input.value = url;
                            document.body.appendChild(input);
                            input.select();
                            document.execCommand('copy');
                            input.remove();
                        }
                        toast.classList.remove('hidden');
                        clearTimeout(toastTimer);
                        toastTimer = setTimeout(function () { toast.classList.add('hidden'); }, 1600);
                        panel.classList.add('hidden');
                    });
                });
            })();
        </script>
    </x-slot>
</x-base>
