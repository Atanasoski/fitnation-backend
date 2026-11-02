{{-- The partner's Stations: QR-stickered spots on the gym floor (ADR-0005). --}}
<section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex items-center justify-between">
        <h2 class="font-display text-sm font-semibold text-gray-900 dark:text-white">Stations</h2>
        <a href="{{ route('admin.stations.create', $partner) }}" class="rounded-lg border border-brand-300 px-3 py-1.5 text-sm text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-400 dark:hover:bg-brand-500/10">New station</a>
    </div>

    @if ($stations->isEmpty())
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No stations yet. Create one to print a QR sticker for a spot on the gym floor.</p>
    @else
        <table class="mt-2 w-full text-sm">
            <thead>
                <tr class="text-left text-theme-xs text-gray-500 dark:text-gray-400">
                    <th class="py-2 font-medium">Station</th>
                    <th class="py-2 font-medium">Short code</th>
                    <th class="py-2 font-medium">Exercises</th>
                    <th class="py-2 font-medium">QR sticker</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($stations as $station)
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        <td class="py-2">
                            <a href="{{ route('admin.stations.edit', [$partner, $station]) }}" class="font-medium text-gray-800 hover:text-brand-600 hover:underline dark:text-white/90 dark:hover:text-brand-400">{{ $station->name }}</a>
                        </td>
                        <td class="py-2 font-mono text-theme-xs text-gray-600 dark:text-gray-300">{{ $station->short_code }}</td>
                        <td class="py-2 text-gray-600 dark:text-gray-300">{{ $station->exercises_count }}</td>
                        <td class="py-2">
                            <a href="{{ route('admin.stations.qr', [$partner, $station, 'svg']) }}" class="text-brand-600 hover:underline dark:text-brand-400">SVG</a>
                            <span class="text-gray-300 dark:text-gray-600">·</span>
                            <a href="{{ route('admin.stations.qr', [$partner, $station, 'png']) }}" class="text-brand-600 hover:underline dark:text-brand-400">PNG</a>
                        </td>
                        <td class="py-2 text-right">
                            <form method="POST" action="{{ route('admin.stations.destroy', [$partner, $station]) }}" class="inline"
                                onsubmit="return confirm(@js("Delete {$station->name}? Printed stickers will show the fallback page; its code is never reused."))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-error-600 hover:underline dark:text-error-400">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>
