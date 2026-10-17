<x-filament-panels::page>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @foreach ([
            'Active licenses' => $active,
            'Suspended licenses' => $suspended,
            'Terminated licenses' => $terminated,
            'Activations' => $activations,
            'Validations (24h)' => $validations24h,
            'Validations (7d)' => $validations7d,
            'Total downloads' => $downloads,
            'Files' => $files,
        ] as $label => $value)
        <div class="rounded-xl bg-white/5 p-4 border border-black/10 dark:border-white/10">
            <div class="text-2xl font-bold">{{ $value }}</div>
            <div class="text-sm opacity-70 mt-1">{{ $label }}</div>
        </div>
        @endforeach
    </div>

    <div class="mt-8">
        <h3 class="text-lg font-semibold mb-3">Top downloads</h3>
        <div class="overflow-x-auto rounded-xl border border-black/10 dark:border-white/10">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left opacity-70">
                        <th class="p-3">Product</th>
                        <th class="p-3">Version</th>
                        <th class="p-3">Downloads</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($topFiles as $file)
                    <tr class="border-t border-black/10 dark:border-white/10">
                        <td class="p-3">{{ $file->product?->name }}</td>
                        <td class="p-3">v{{ $file->version }}</td>
                        <td class="p-3">{{ $file->download_count }}</td>
                    </tr>
                    @empty
                    <tr class="border-t border-black/10 dark:border-white/10">
                        <td class="p-3 opacity-60" colspan="3">No files yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
