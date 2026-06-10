<x-base title="Sign in — TubeCast">
    <main class="mx-auto flex min-h-full max-w-md flex-col justify-center px-6 py-16">
        <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-8">
            <h1 class="text-2xl font-semibold text-white">TubeCast</h1>
            <p class="mt-1 text-sm text-slate-500">Sign in to manage your library</p>

            <div :if="$error ?? false" class="mt-6 rounded-lg border border-rose-900/50 bg-rose-950/30 px-4 py-3 text-sm text-rose-300">
                Invalid username or password.
            </div>

            <form method="post" action="/login" class="mt-6 space-y-4">
                <label class="block">
                    <span class="text-xs font-medium uppercase tracking-wide text-slate-500">Username</span>
                    <input type="text" name="username" required autocomplete="username"
                           class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-indigo-500 focus:outline-none" />
                </label>
                <label class="block">
                    <span class="text-xs font-medium uppercase tracking-wide text-slate-500">Password</span>
                    <input type="password" name="password" required autocomplete="current-password"
                           class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-indigo-500 focus:outline-none" />
                </label>
                <button type="submit"
                        class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-500 transition">
                    Sign in
                </button>
            </form>
        </div>
    </main>
</x-base>
