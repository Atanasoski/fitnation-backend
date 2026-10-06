{{-- Devices and push: Fleet::summary()['platform'] and ['push']. --}}
@php
    $devices = $fleet['platform']['ios'] + $fleet['platform']['android'];
    $iosShare = $devices > 0 ? round($fleet['platform']['ios'] / $devices * 100, 1) : 0;
    $reachable = $fleet['push']['on'] + $fleet['push']['off'];
@endphp
<section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <header class="border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:px-6">
        <h2 class="font-display text-base font-semibold text-gray-800 dark:text-white/90">Devices and push</h2>
        <p class="text-theme-xs text-gray-500 dark:text-gray-400">Each Device is one signed-in app session.</p>
    </header>

    <div class="grid divide-y divide-gray-100 dark:divide-gray-800 sm:grid-cols-2 sm:divide-x sm:divide-y-0">
        <div class="p-5 sm:px-6">
            <div class="text-theme-xs text-gray-500 dark:text-gray-400">Platform</div>
            @if ($devices > 0)
                <x-admin.share-bar class="mt-2" :percent="$iosShare" :height="3" rest="blue-light" />
                <div class="mt-2 flex justify-between text-sm text-gray-700 dark:text-gray-300">
                    <span>iOS {{ number_format($fleet['platform']['ios']) }}</span>
                    <span>Android {{ number_format($fleet['platform']['android']) }}</span>
                </div>
            @else
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">No Devices yet.</div>
            @endif
        </div>
        <div class="p-5 sm:px-6">
            <div class="text-theme-xs text-gray-500 dark:text-gray-400">Push Switch on</div>
            <div class="font-display text-2xl font-semibold text-gray-900 dark:text-white">
                {{ $reachable > 0 ? round($fleet['push']['on'] / $reachable * 100).'%' : '—' }}
            </div>
            <div class="text-theme-xs text-gray-400">
                {{ number_format($fleet['push']['on']) }} of {{ number_format($reachable) }} app {{ Str::plural('user', $reachable) }} with a Device
            </div>
        </div>
    </div>
</section>
