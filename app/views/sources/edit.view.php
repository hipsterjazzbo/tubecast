<x-base :title="'Edit · ' . ($source->title ?? 'Source')">
    <main class="mx-auto max-w-2xl px-6 py-8">
        <header class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <a href="/sources/{{ $source->id }}" class="text-sm text-slate-500 hover:text-slate-300 transition">← Source</a>
                <h1 class="mt-2 text-2xl font-semibold text-white">Edit source</h1>
                <p class="mt-1 text-sm text-slate-500 truncate max-w-xl">{{ $source->title ?? $source->url }}</p>
            </div>
            <x-nav />
        </header>

        <x-source-form
            :formAction="'/sources/' . $source->id . '/settings'"
            :cancelHref="'/sources/' . $source->id"
            :profiles="$profiles"
            :source="$source"
            :sourceFilters="$sourceFilters"
            :isEdit="true"
        />
    </main>
</x-base>
