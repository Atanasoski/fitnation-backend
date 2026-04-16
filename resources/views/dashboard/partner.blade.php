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
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
            <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Expiring soon</p>
            <p class="mt-2 text-2xl font-semibold {{ $expiringSoonCount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">{{ $expiringSoonCount }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">within 7 days</p>
        </div>
    </div>

    @if ($recentSubscriptions->isNotEmpty())
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">Recent subscription activity</h3>
            </div>
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
        </div>
    @endif
</div>
@endsection
