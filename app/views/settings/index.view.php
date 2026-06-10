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
                <input name="ytDlpCookiesFile" value="{{ $cookiesFile }}" class="mt-1.5 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm" placeholder="/data/config/cookies.txt">
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

        <section :if="$devToolsEnabled" class="space-y-4 rounded-xl border border-rose-500/30 bg-rose-950/20 p-6">
            <h2 class="text-sm font-medium text-rose-300 uppercase tracking-wide">Developer tools</h2>
            <p class="text-sm text-slate-400">Only visible when <code class="text-slate-300">ENVIRONMENT</code> is not production or staging.</p>

            <p :if="$nukeResult !== null" class="text-sm text-emerald-300 rounded-lg bg-emerald-950/40 border border-emerald-500/30 px-3 py-2">
                Nuked {{ $nukeResult['files'] }} files and reset {{ $nukeResult['items'] }} media items. Pending download commands were cleared.
            </p>

            <form method="post" action="/settings/dev/nuke-downloads"
                  onsubmit="return confirm('Delete ALL downloaded video and podcast files? This cannot be undone.');">
                <p class="text-sm text-slate-400 mb-4">
                    Wipes everything under downloads and podcast storage, resets episode statuses to discovered, and clears queued download commands.
                    Active yt-dlp processes may need a container restart if downloads are mid-flight.
                </p>
                <button type="submit" class="text-sm px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-500 text-white transition">
                    Nuke all downloads
                </button>
            </form>
        </section>
    </main>
</x-base>
