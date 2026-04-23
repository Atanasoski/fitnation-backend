@extends('layouts.app')

@section('title', 'Gym Dashboard')

@section('content')
<div class="p-6">
    <div class="mb-6">
        <div class="flex items-center mb-3">
            @if($partner->identity && $partner->identity->logo_url)
                <img src="{{ $partner->identity->logo_url }}" alt="{{ $partner->name }}" class="w-16 h-16 rounded mr-4 object-cover">
            @endif
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $partner->name }} Dashboard
                </h2>
                    <p class="text-gray-600 dark:text-gray-400">Welcome back, {{ Auth::user()->name }} - Gym Manager</p>
            </div>
        </div>
    </div>

    <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
            <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Members</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $partner->users_count }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
            <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Subscription plans</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $partner->subscription_plans_count }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
            <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Active subscriptions</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $activeSubscriptionsCount }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
            <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Monthly revenue</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">${{ number_format((float) $monthlyRecurringRevenue, 2) }}</p>
        </div>
        <a
            href="{{ route('dashboard', ['expiring' => 1]) }}"
            class="block rounded-xl border border-gray-200 bg-white p-5 transition-colors hover:border-amber-300 hover:bg-amber-50/50 dark:border-gray-800 dark:bg-white/3 dark:hover:border-amber-700 dark:hover:bg-amber-950/20"
        >
            <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Expiring soon</p>
            <p class="mt-2 text-2xl font-semibold {{ $expiringSoonCount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">{{ $expiringSoonCount }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">within 7 days — view in table</p>
        </a>
    </div>

    <div class="mb-4 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
        <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
                <div>
                    <h3 class="text-sm font-medium text-gray-900 dark:text-white">Subscription activity</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Filtered by subscription start date (created), except when viewing expiring subscriptions.</p>
                </div>
                @if ($expiringFilter)
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-900 dark:bg-amber-900/40 dark:text-amber-200">
                            Filtered: expiring within 7 days
                            <a href="{{ route('dashboard') }}" class="ml-1 rounded-full p-0.5 hover:bg-amber-200/80 dark:hover:bg-amber-800/50" title="Clear">&times;</a>
                        </span>
                    </div>
                @endif
            </div>
        </div>
        <div class="px-5 py-4">
            <form method="get" action="{{ route('dashboard') }}" class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end">
                <div class="grid w-full gap-4 sm:grid-cols-2 lg:max-w-xl lg:flex-1">
                    <div>
                        <label for="dashboard_start_date" class="block text-xs font-medium text-gray-500 dark:text-gray-400">From</label>
                        <input
                            type="date"
                            name="start_date"
                            id="dashboard_start_date"
                            value="{{ $activityFilters['start_date_input'] }}"
                            class="dashboard-date-filter mt-1 block w-full cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-white dark:focus:ring-white"
                            onclick="this.showPicker?.()"
                        >
                    </div>
                    <div>
                        <label for="dashboard_end_date" class="block text-xs font-medium text-gray-500 dark:text-gray-400">To</label>
                        <input
                            type="date"
                            name="end_date"
                            id="dashboard_end_date"
                            value="{{ $activityFilters['end_date_input'] }}"
                            class="dashboard-date-filter mt-1 block w-full cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-white dark:focus:ring-white"
                            onclick="this.showPicker?.()"
                        >
                    </div>
                </div>
                <div class="w-full lg:max-w-xs">
                    <label for="dashboard_plan_id" class="block text-xs font-medium text-gray-500 dark:text-gray-400">Plan</label>
                    <select
                        name="plan_id"
                        id="dashboard_plan_id"
                        class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-white dark:focus:ring-white"
                    >
                        <option value="">All plans</option>
                        @foreach ($partnerPlans as $planOption)
                            <option value="{{ $planOption->id }}" @selected((int) ($activityFilters['plan_id'] ?? 0) === (int) $planOption->id)>{{ $planOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100">
                        Apply
                    </button>
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
            <h3 class="text-sm font-medium text-gray-900 dark:text-white">Results</h3>
        </div>
        @if ($recentSubscriptions->isNotEmpty())
            <div class="max-w-full overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Member</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Plan</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Price</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentSubscriptions as $subscription)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                <td class="px-5 py-3 text-sm text-gray-800 dark:text-white/90">{{ $subscription->user?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-800 dark:text-white/90">{{ $subscription->subscriptionPlan?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-800 dark:text-white/90">
                                    @if ($subscription->subscriptionPlan)
                                        ${{ number_format((float) $subscription->subscriptionPlan->price, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-sm capitalize text-gray-600 dark:text-gray-400">{{ $subscription->derivedStateLabel() }}</td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $subscription->updated_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($recentSubscriptions->hasPages())
                <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-800">
                    {{ $recentSubscriptions->links() }}
                </div>
            @endif
        @else
            <div class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                No subscriptions match your filters.
            </div>
        @endif
    </div>
</div>
@endsection
