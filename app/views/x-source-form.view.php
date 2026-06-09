<form method="post" action="{{ $formAction }}" class="space-y-6 rounded-xl border border-slate-800 bg-slate-900/40 p-6">
    <label class="block" :if="!$isEdit">
        <span class="text-sm text-slate-300">YouTube URL</span>
        <input name="url" type="url" required
               class="mt-1.5 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm"
               placeholder="https://www.youtube.com/@channel or playlist URL">
    </label>

    <div class="block" :if="$isEdit">
        <span class="text-sm text-slate-300">YouTube URL</span>
        <p class="mt-1.5 text-sm text-slate-400 break-all">{{ $source->url }}</p>
    </div>

    <label class="block">
        <span class="text-sm text-slate-300">Display title</span>
        <input type="text" name="title" :value="$source->title ?? ''"
               placeholder="Friendly name for TubeCast and RSS feeds"
               class="mt-1.5 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm">
    </label>

    <div class="block">
        <span class="text-sm text-slate-300">Also include</span>
        <p class="mt-1 text-xs text-slate-500">Long-form videos are always indexed. Videos under 2 minutes count as shorts.</p>
        <div class="mt-2 flex flex-wrap gap-6">
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="includeShorts" value="1" :checked="$source->includeShorts ?? false"
                       class="rounded border-slate-600 bg-slate-800 text-indigo-500">
                <span class="text-sm text-slate-300">Shorts</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="includeLive" value="1" :checked="$source->includeLive ?? false"
                       class="rounded border-slate-600 bg-slate-800 text-indigo-500">
                <span class="text-sm text-slate-300">Live streams</span>
            </label>
        </div>
    </div>

    <div class="block">
        <span class="text-sm text-slate-300">What to save</span>
        <div class="mt-2 flex flex-wrap gap-6">
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="saveVideo" value="1" :checked="$source->saveVideo ?? false"
                       class="rounded border-slate-600 bg-slate-800 text-indigo-500">
                <span class="text-sm text-slate-300">Video</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="saveAudio" value="1" :checked="$source->saveAudio ?? false"
                       class="rounded border-slate-600 bg-slate-800 text-indigo-500">
                <span class="text-sm text-slate-300">Audio (M4A)</span>
            </label>
        </div>
        <p class="mt-1 text-xs text-slate-500">Leave both unchecked to index and test filters without downloading.</p>
    </div>

    <label class="block">
        <span class="text-sm text-slate-300">Download mode</span>
        <select name="downloadMode" class="mt-1.5 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm">
            <option value="auto" :selected="($sourceFilters->downloadMode->value ?? 'auto') === 'auto'">Automatic — queue matching episodes</option>
            <option value="manual" :selected="($sourceFilters->downloadMode->value ?? 'auto') === 'manual'">Manual — pick episodes to download</option>
        </select>
    </label>

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block">
            <span class="text-sm text-slate-300">Minimum duration (minutes)</span>
            <input type="number" name="minDurationMinutes" min="0" :value="$sourceFilters->minDurationMinutes() ?? ''"
                   placeholder="Optional"
                   class="mt-1.5 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm">
        </label>
        <label class="block">
            <span class="text-sm text-slate-300">Maximum duration (minutes)</span>
            <input type="number" name="maxDurationMinutes" min="0" :value="$sourceFilters->maxDurationMinutes() ?? ''"
                   placeholder="Optional"
                   class="mt-1.5 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm">
        </label>
    </div>

    <label class="block">
        <span class="text-sm text-slate-300">Title regex</span>
        <input type="text" name="titleRegex" :value="$sourceFilters->titleRegex ?? ''"
               placeholder="Optional, e.g. /Campaign/i"
               class="mt-1.5 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm font-mono">
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">Media profile</span>
        <select name="mediaProfileId" class="mt-1.5 w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 px-3 py-2 text-sm">
            <option value="">Default</option>
            <option :foreach="$profiles as $profile" value="{{ $profile->id }}"
                    :selected="$source->mediaProfileId !== null && $source->mediaProfileId == $profile->id">{{ $profile->name }}</option>
        </select>
    </label>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="text-sm px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition">Save</button>
        <a href="{{ $cancelHref }}" class="text-sm px-4 py-2 rounded-lg border border-slate-700 text-slate-300 hover:bg-slate-800 transition">Cancel</a>
    </div>
</form>
