<div class="p-4 space-y-3">
    <p class="text-sm opacity-70">
        {{ __('licenses::messages.downloads_intro') }}
    </p>

    @foreach ($files as $file)
    <div class="rounded-lg border border-black/10 dark:border-white/10 p-4 flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="font-semibold">v{{ $file->version }}</span>
                @if ($loop->first)
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-500">
                    {{ __('licenses::messages.latest') }}
                </span>
                @endif
                <span class="text-xs opacity-60">{{ $file->created_at->format('Y-m-d') }}</span>
            </div>

            @if ($file->changelog)
            <p class="text-sm opacity-70 mt-1">{{ $file->changelog }}</p>
            @endif

            <p class="text-xs opacity-50 mt-1 truncate">
                {{ $file->filename }}@if ($file->size) &middot; {{ $file->formattedSize() }}@endif
            </p>
        </div>

        <a href="{{ route('extensions.servers.licenses.download', $file->id) }}"
           class="shrink-0 px-4 py-2 rounded-lg bg-primary text-inverted font-semibold text-sm text-center hover:opacity-90 transition w-full sm:w-fit">
            {{ __('licenses::messages.download') }}
        </a>
    </div>
    @endforeach
</div>
