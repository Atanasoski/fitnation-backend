@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <x-common.page-breadcrumb pageTitle="Users" />

    @php
        $select = 'rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300';
        $filtered = $filters['partner'] !== null || $filters['activity'] !== null;
    @endphp

    <form method="GET" action="{{ route('admin.users.index') }}" class="mb-4 flex flex-wrap items-center gap-2 text-sm">
        <select name="partner" class="{{ $select }}" onchange="this.form.submit()" aria-label="Partner">
            <option value="">All partners</option>
            @foreach ($partners as $partner)
                <option value="{{ $partner->id }}" @selected($filters['partner'] === $partner->id)>{{ $partner->name }}</option>
            @endforeach
        </select>
        <select name="activity" class="{{ $select }}" onchange="this.form.submit()" aria-label="Activity Status">
            <option value="">Any Activity Status</option>
            @foreach (\App\Enums\ActivityStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected($filters['activity'] === $status)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <noscript><button type="submit" class="rounded-lg bg-brand-500 px-3 py-1.5 text-theme-xs font-medium text-white">Filter</button></noscript>
        @if ($filtered)
            <a href="{{ route('admin.users.index') }}" class="text-theme-xs text-brand-600 hover:underline dark:text-brand-400">Clear filters</a>
        @endif
        <span class="ml-auto text-theme-xs text-gray-500 dark:text-gray-400">{{ number_format($users->total()) }} {{ Str::plural('user', $users->total()) }}</span>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($users->isEmpty())
            <p class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">
                {{ $filtered ? 'No users match these filters.' : 'No users yet.' }}
            </p>
        @else
            <div class="max-w-full overflow-x-auto custom-scrollbar">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-theme-xs text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-2 font-medium sm:px-6">Name</th>
                            <th class="px-3 py-2 font-medium">Partner</th>
                            <th class="px-3 py-2 font-medium">Signed up</th>
                            <th class="px-3 py-2 font-medium">Last Completed Session</th>
                            <th class="px-3 py-2 font-medium">Sessions 30d</th>
                            <th class="px-5 py-2 font-medium sm:px-6">Activity Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            @php $last = $user->last_completed_session_at ? \Illuminate\Support\Carbon::parse($user->last_completed_session_at) : null; @endphp
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="px-5 py-3 sm:px-6">
                                    <div class="font-medium text-gray-800 dark:text-white/90">{{ $user->name }}</div>
                                    <div class="text-theme-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</div>
                                </td>
                                <td class="px-3 py-3 text-gray-600 dark:text-gray-400">{{ $user->partner?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-gray-600 dark:text-gray-400">{{ $user->created_at->format('j M Y') }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-gray-600 dark:text-gray-400" @if ($last) title="{{ $last->diffForHumans() }}" @endif>{{ $last?->format('j M Y') ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-600 dark:text-gray-400">{{ $user->completed_sessions_30d }}</td>
                                <td class="px-5 py-3 sm:px-6"><x-admin.activity-chip :status="$statuses[$user->id]" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($users->hasPages())
                <div class="border-t border-gray-100 px-5 py-3 dark:border-gray-800 sm:px-6">
                    {{ $users->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
