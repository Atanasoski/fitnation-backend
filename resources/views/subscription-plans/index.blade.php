@extends('layouts.app')

@section('title', 'Subscription plans')

@section('content')
    <x-common.page-breadcrumb pageTitle="Subscription plans" />

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-500 dark:text-gray-400">Billing plans members can be assigned to</p>
        <a href="{{ route('subscription-plans.create') }}"
            class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100">
            New plan
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
        <div class="max-w-full overflow-x-auto">
            <table class="w-full min-w-[700px]">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Name</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Price</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Billing</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Members</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Active</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subscriptionPlans as $plan)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="px-5 py-4 text-sm text-gray-800 dark:text-white/90">{{ $plan->name }}</td>
                            <td class="px-5 py-4 text-sm text-gray-800 dark:text-white/90">${{ number_format((float) $plan->price, 2) }}</td>
                            <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">{{ str($plan->billing_cycle?->value)->replace('_', ' ')->title() }}</td>
                            <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $plan->subscriptions_count }}</td>
                            <td class="px-5 py-4 text-sm">
                                @if ($plan->is_active)
                                    <span class="text-emerald-600 dark:text-emerald-400">Yes</span>
                                @else
                                    <span class="text-gray-500">No</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right text-sm">
                                <a href="{{ route('subscription-plans.edit', $plan) }}" class="text-gray-900 underline dark:text-white">Edit</a>
                                <form action="{{ route('subscription-plans.destroy', $plan) }}" method="POST" class="inline ms-3" onsubmit="return confirm('Delete this plan?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 underline dark:text-red-400">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-sm text-gray-500">No subscription plans yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
