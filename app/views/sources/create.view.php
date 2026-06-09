<x-base title="Add source">
    <main class="mx-auto max-w-2xl px-6 py-8">
        <header class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <a href="/sources" class="text-sm text-slate-500 hover:text-slate-300 transition">← Sources</a>
                <h1 class="mt-2 text-2xl font-semibold text-white">Add source</h1>
            </div>
            <x-nav />
        </header>

        <x-source-form
            :formAction="'/sources'"
            :cancelHref="'/sources'"
            :profiles="$profiles"
            :source="$source"
            :sourceFilters="$sourceFilters"
            :isEdit="false"
        />
    </main>
</x-base>
