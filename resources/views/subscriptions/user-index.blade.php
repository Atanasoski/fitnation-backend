@extends('layouts.app')

@section('title', 'Subscriptions — '.$user->name)

@section('content')
    <div class="p-6">
        <x-common.page-breadcrumb
            pageTitle="Subscriptions"
            :items="[
                ['label' => 'Users', 'url' => route('users.index')],
                ['label' => $user->name, 'url' => route('users.show', $user)],
            ]"
        />

        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                All subscription periods for this member (active, upcoming, expired, and cancelled).
            </p>
            <a
                href="{{ route('subscriptions.create', ['user_id' => $user->id, 'redirect_after' => 'member_subscriptions']) }}"
                class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100"
            >
                Assign subscription
            </a>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
            <div class="max-w-full overflow-x-auto">
                <table class="w-full min-w-[720px]">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Plan</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Starts</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Ends</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Cancelled</th>
                            <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subscriptions as $subscription)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-5 py-4 text-sm text-gray-900 dark:text-white">
                                    {{ $subscription->subscriptionPlan?->name ?? '—' }}
                                </td>
                                <td class="px-5 py-4 text-sm">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $subscription->statusBadgeClasses() }}">
                                        {{ $subscription->derivedStateLabel() }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $subscription->starts_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? '—' }}
                                </td>
                                <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $subscription->ends_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? '—' }}
                                </td>
                                <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $subscription->cancelled_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? '—' }}
                                </td>
                                <td class="px-5 py-4 text-right text-sm">
                                    @if ($subscription->canBeCancelledByPartnerAdmin())
                                        <form
                                            action="{{ route('subscriptions.destroy', $subscription) }}"
                                            method="POST"
                                            class="inline"
                                            onsubmit="return confirm('Cancel this subscription?');"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 underline dark:text-red-400">Cancel</button>
                                        </form>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No subscriptions yet. Use Assign subscription to add one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
