{{-- App versions in use: Fleet::summary()['versions'] and the Old Build count. --}}
@php
    $totalDevices = max(1, collect($fleet['versions'])->sum('devices'));
    $oldBuildUsers = $fleet['old_build_users'];
@endphp
<section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <header class="border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:px-6">
        <h2 class="font-display text-base font-semibold text-gray-800 dark:text-white/90">App versions in use</h2>
        <p class="text-theme-xs text-gray-500 dark:text-gray-400">
            Devices by the app version they last reported. A production version older than the two newest production versions is an Old Build; preview and development builds never are.
        </p>
    </header>

    @if ($fleet['versions'] === [])
        <p class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">No Device has registered yet.</p>
    @else
        <div class="max-w-full overflow-x-auto custom-scrollbar">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-theme-xs text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                    <tr>
                        <th class="px-5 py-2 font-medium sm:px-6">Version</th>
                        <th class="px-3 py-2 font-medium">Build</th>
                        <th class="px-3 py-2 text-right font-medium">iOS</th>
                        <th class="px-3 py-2 text-right font-medium">Android</th>
                        <th class="w-1/3 px-5 py-2 font-medium sm:px-6">Share</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($fleet['versions'] as $row)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="whitespace-nowrap px-5 py-2.5 font-medium text-gray-800 dark:text-white/90 sm:px-6">
                                {{ $row['version'] ?? 'unknown' }}
                                @if ($row['latest'])
                                    <span class="ml-1 rounded-full bg-success-50 px-1.5 text-theme-xs font-normal text-success-700 dark:bg-success-500/15 dark:text-success-400">latest</span>
                                @endif
                                @if ($row['old'])
                                    <span class="ml-1 rounded-full bg-orange-50 px-1.5 text-theme-xs font-normal text-orange-700 dark:bg-orange-500/15 dark:text-orange-400">old</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-gray-600 dark:text-gray-400"><code class="text-theme-xs">{{ $row['build_profile'] ?? 'unknown' }}</code></td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($row['ios']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($row['android']) }}</td>
                            <td class="px-5 py-2.5 sm:px-6">
                                <x-admin.share-bar :percent="round($row['devices'] / $totalDevices * 100, 1)" :tone="$row['old'] ? 'orange' : 'brand'"
                                    title="{{ number_format($row['devices']) }} {{ Str::plural('Device', $row['devices']) }}" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="border-t border-gray-100 px-5 py-3 text-sm dark:border-gray-800 sm:px-6">
        @if ($oldBuildUsers > 0)
            <a href="{{ route('admin.users.index', ['old_build' => 1]) }}" class="text-brand-600 hover:underline dark:text-brand-400">
                {{ number_format($oldBuildUsers) }} {{ $oldBuildUsers === 1 ? 'user is' : 'users are' }} on an Old Build → see them in Users
            </a>
        @else
            <span class="text-gray-500 dark:text-gray-400">No users are on an Old Build.</span>
        @endif
        <span class="block text-theme-xs text-gray-400">Counted by each user's most recently seen Device. Staff are not counted.</span>
    </div>
</section>
