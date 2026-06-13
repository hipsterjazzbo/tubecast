<x-base title="Settings">
    <main class="mx-auto max-w-2xl px-6 py-8">
        <header class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h1 class="text-2xl font-semibold text-white">Settings</h1>
            <x-nav />
        </header>

        <section class="mb-8 space-y-4 rounded-xl border border-slate-800 bg-slate-900/40 p-6">
            <h2 class="text-sm font-medium text-slate-300 uppercase tracking-wide">yt-dlp (environment)</h2>
            <p class="text-sm text-slate-500">These values come from <code class="text-slate-400">.env</code> and require a restart to change.</p>
            <dl class="grid grid-cols-2 gap-2 text-sm">
                <dt class="text-slate-500">Binary</dt><dd class="text-slate-200">{{ $ytDlpBinary }}</dd>
                <dt class="text-slate-500">Worker concurrency</dt><dd class="text-slate-200">{{ $workerConcurrency }}</dd>
                <dt class="text-slate-500">Sleep interval</dt><dd class="text-slate-200">{{ $sleepInterval }}s</dd>
                <dt class="text-slate-500">Sleep requests</dt><dd class="text-slate-200">{{ $sleepRequests }}s</dd>
            </dl>
        </section>

        <form method="post" action="/settings/yt-dlp" class="mb-8 space-y-4 rounded-xl border border-slate-800 bg-slate-900/40 p-6">
            <h2 class="text-sm font-medium text-slate-300 uppercase tracking-wide">yt-dlp overrides</h2>
            <label class="block">
                <span class="text-sm text-slate-300">Cookies file</span>
                <input name="ytDlpCookiesFile" value="{{ $cookiesFile }}"
                       class="mt-1.5 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm"
                       placeholder="/config/cookies.txt">
            </label>
            <label class="block">
                <span class="text-sm text-slate-300">Proxy</span>
                <input name="ytDlpProxy" value="{{ $proxy }}" class="mt-1.5 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm" placeholder="http://127.0.0.1:8080">
            </label>
            <button type="submit" class="text-sm px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition">Save</button>
        </form>

        <form method="post" action="/settings/youtube-api" class="mb-8 space-y-4 rounded-xl border border-slate-800 bg-slate-900/40 p-6">
            <h2 class="text-sm font-medium text-slate-300 uppercase tracking-wide">YouTube Data API</h2>
            <p class="text-sm text-slate-500">Used for full indexing via the YouTube Data API instead of yt-dlp. Downloads still use yt-dlp. You can also set <code class="text-slate-400">YOUTUBE_API_KEY</code> in the environment.</p>
            <p :if="$youtubeApiConfigured" class="text-sm text-emerald-300">API key is configured.</p>
            <label class="block">
                <span class="text-sm text-slate-300">API key</span>
                <input name="youtubeApiKey" value="{{ $youtubeApiKey }}" type="password" autocomplete="off" class="mt-1.5 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm" placeholder="AIza...">
            </label>
            <button type="submit" class="text-sm px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition">Save</button>
        </form>


        <section id="media-servers" class="mb-8 space-y-4 rounded-xl border border-slate-800 bg-slate-900/40 p-6">
            <h2 class="text-sm font-medium text-slate-300 uppercase tracking-wide">Media servers</h2>
            <p class="text-sm text-slate-500">Connect Plex or Jellyfin, sync libraries, and notify them when downloads complete.</p>

            <div :if="$mediaServers === []" class="text-sm text-slate-400">No media servers configured yet.</div>

            <div :foreach="$mediaServers as $row" class="rounded-lg border border-slate-800 bg-slate-950/40 p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-sm font-medium text-slate-200">{{ $row->server->name }} <span class="text-slate-500">({{ $row->server->type->value }})</span></p>
                        <p class="text-xs text-slate-500">{{ $row->server->baseUrl }} · {{ $row->libraryCount }} libraries</p>
                        <p :if="$row->server->lastSyncError !== null" class="text-xs text-rose-400 mt-1">{{ $row->server->lastSyncError }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <form method="post" action="/settings/media-servers/{{ $row->server->id }}/test">
                            <button type="submit" class="text-xs px-3 py-1.5 rounded-lg border border-slate-700 text-slate-300 hover:bg-slate-800">Test</button>
                        </form>
                        <form method="post" action="/settings/media-servers/{{ $row->server->id }}/sync">
                            <button type="submit" class="text-xs px-3 py-1.5 rounded-lg border border-slate-700 text-slate-300 hover:bg-slate-800">Sync libraries</button>
                        </form>
                        <form method="post" action="/settings/media-servers/{{ $row->server->id }}/delete" onsubmit="return confirm('Delete this media server?');">
                            <button type="submit" class="text-xs px-3 py-1.5 rounded-lg border border-rose-500/40 text-rose-300 hover:bg-rose-950/40">Delete</button>
                        </form>
                    </div>
                </div>
                <form method="post" action="/settings/media-servers/{{ $row->server->id }}" class="grid gap-3 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="text-xs text-slate-400">Name</span>
                        <input name="name" value="{{ $row->server->name }}" class="mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm">
                    </label>
                    <label class="block">
                        <span class="text-xs text-slate-400">Type</span>
                        <select name="type" class="mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm">
                            <option value="plex" :selected="$row->server->type->value === 'plex'">Plex</option>
                            <option value="jellyfin" :selected="$row->server->type->value === 'jellyfin'">Jellyfin</option>
                        </select>
                    </label>
                    <label class="block flex items-end gap-2 pb-2">
                        <input type="checkbox" name="enabled" value="1" :checked="$row->server->enabled" class="rounded border-slate-600 bg-slate-800 text-indigo-500">
                        <span class="text-sm text-slate-300">Enabled</span>
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-xs text-slate-400">Base URL</span>
                        <input name="baseUrl" value="{{ $row->server->baseUrl }}" class="mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-xs text-slate-400">API token</span>
                        <input name="apiToken" value="{{ $row->server->apiToken }}" type="password" autocomplete="off" class="mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm">
                    </label>
                    <label class="block">
                        <span class="text-xs text-slate-400">TubeCast video root</span>
                        <input name="tubecastVideoRoot" value="{{ $row->server->tubecastVideoRoot }}" class="mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm">
                    </label>
                    <label class="block">
                        <span class="text-xs text-slate-400">TubeCast audio root</span>
                        <input name="tubecastAudioRoot" value="{{ $row->server->tubecastAudioRoot }}" class="mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm">
                    </label>
                    <div class="sm:col-span-2">
                        <button type="submit" class="text-sm px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition">Save server</button>
                    </div>
                </form>
            </div>

            <form method="post" action="/settings/media-servers" class="mt-6 space-y-3 border-t border-slate-800 pt-6">
                <h3 class="text-sm text-slate-300">Add media server</h3>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="text-xs text-slate-400">Name</span>
                        <input name="name" required class="mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm" placeholder="Living room Plex">
                    </label>
                    <label class="block">
                        <span class="text-xs text-slate-400">Type</span>
                        <select name="type" class="mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm">
                            <option value="plex">Plex</option>
                            <option value="jellyfin">Jellyfin</option>
                        </select>
                    </label>
                    <label class="block flex items-end gap-2 pb-2">
                        <input type="checkbox" name="enabled" value="1" checked class="rounded border-slate-600 bg-slate-800 text-indigo-500">
                        <span class="text-sm text-slate-300">Enabled</span>
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-xs text-slate-400">Base URL</span>
                        <input name="baseUrl" required class="mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm" placeholder="http://plex:32400">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-xs text-slate-400">API token</span>
                        <input name="apiToken" required type="password" autocomplete="off" class="mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm">
                    </label>
                    <label class="block">
                        <span class="text-xs text-slate-400">TubeCast video root</span>
                        <input name="tubecastVideoRoot" required value="{{ $defaultVideoRoot }}" class="mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm">
                    </label>
                    <label class="block">
                        <span class="text-xs text-slate-400">TubeCast audio root</span>
                        <input name="tubecastAudioRoot" required value="{{ $defaultAudioRoot }}" class="mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm">
                    </label>
                </div>
                <button type="submit" class="text-sm px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition">Add server</button>
            </form>
        </section>

        <form id="metadata-providers" method="post" action="/settings/metadata-providers" class="mb-8 space-y-4 rounded-xl border border-slate-800 bg-slate-900/40 p-6">
            <h2 class="text-sm font-medium text-slate-300 uppercase tracking-wide">Metadata providers</h2>
            <p class="text-sm text-slate-500">Optional API keys for linking sources to TMDB or TVDB series metadata.</p>
            <label class="block">
                <span class="text-sm text-slate-300">TMDB API key</span>
                <input name="tmdbApiKey" value="{{ $tmdbApiKey }}" type="password" autocomplete="off" class="mt-1.5 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="text-sm text-slate-300">TVDB API key</span>
                <input name="tvdbApiKey" value="{{ $tvdbApiKey }}" type="password" autocomplete="off" class="mt-1.5 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm">
            </label>
            <button type="submit" class="text-sm px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition">Save</button>
        </form>
        <section :if="$devToolsEnabled" class="space-y-4 rounded-xl border border-rose-500/30 bg-rose-950/20 p-6">
            <h2 class="text-sm font-medium text-rose-300 uppercase tracking-wide">Developer tools</h2>
            <p class="text-sm text-slate-400">Only visible when <code class="text-slate-300">ENVIRONMENT</code> is not production or staging.</p>

            <p :if="$nukeResult !== null" class="text-sm text-emerald-300 rounded-lg bg-emerald-950/40 border border-emerald-500/30 px-3 py-2">
                Nuked {{ $nukeResult['files'] }} files and reset {{ $nukeResult['items'] }} media items. Pending download commands were cleared.
            </p>

            <form method="post" action="/settings/dev/nuke-media"
                  onsubmit="return confirm('Delete ALL video and audio files? This cannot be undone.');">
                <p class="text-sm text-slate-400 mb-4">
                    Wipes everything under video and audio storage, resets episode statuses to discovered, and clears
                    queued download commands.
                    Active yt-dlp processes may need a container restart if transfers are mid-flight.
                </p>
                <button type="submit" class="text-sm px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-500 text-white transition">
                    Nuke video and audio
                </button>
            </form>
        </section>
    </main>
</x-base>
