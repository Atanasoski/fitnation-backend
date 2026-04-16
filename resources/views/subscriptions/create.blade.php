@extends('layouts.app')

@section('title', 'Assign subscription')

@section('content')
    <div class="p-6">
        <x-common.page-breadcrumb pageTitle="Assign subscription" :items="[['label' => 'Users', 'url' => route('users.index')]]" />

        @if ($plans->isEmpty())
            <div class="mb-6">
                <x-ui.alert variant="warning" title="No active subscription plans">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Create an active
                        <a href="{{ route('subscription-plans.create') }}" class="font-medium text-gray-800 underline decoration-gray-400 underline-offset-2 hover:text-gray-900 dark:text-white dark:hover:text-white">
                            subscription plan
                        </a>
                        before assigning members.
                    </p>
                </x-ui.alert>
            </div>
        @else
            <div class="mx-auto max-w-4xl">
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900/30">
                    <div class="p-6 sm:p-8">
                        <div class="mb-8">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Assignment</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Link a member to a billing plan
                            </p>
                        </div>

                        <form action="{{ route('subscriptions.store') }}" method="POST" class="space-y-6">
                            @csrf

                            <input type="hidden" name="redirect_after" value="{{ old('redirect_after', $redirectAfter ?? 'users') }}">
                            <input type="hidden" name="user_id" value="{{ old('user_id', request('user_id')) }}">

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Plan <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <select
                                        name="subscription_plan_id"
                                        id="subscription_plan_id"
                                        required
                                        class="appearance-none block w-full rounded-md border border-gray-300 bg-white py-2.5 pl-3 pr-10 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-700 dark:bg-transparent dark:text-white"
                                    >
                                        <option value="">Select plan</option>
                                        @foreach ($plans as $plan)
                                            <option value="{{ $plan->id }}" @selected(old('subscription_plan_id') == $plan->id)>
                                                {{ $plan->name }} — {{ str($plan->billing_cycle->value)->replace('_', ' ')->title() }} — ${{ number_format((float) $plan->price, 2) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-400">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    @if ($memberActiveSubscription)
                                        @if ($memberActiveSubscription->ends_at)
                                            This member’s current subscription ends on
                                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $memberActiveSubscription->ends_at->timezone(config('app.timezone'))->format('F j, Y') }}</span>.
                                            The new subscription will start after it expires. The end date of the new period is calculated from the plan’s billing cycle (for example, monthly adds one month from that start date).
                                        @else
                                            This member has an active subscription without an end date. The new subscription will start immediately. The end date is calculated from the plan’s billing cycle.
                                        @endif
                                    @else
                                        Start time is set to now. The end date is calculated from the plan’s billing cycle (for example, monthly adds one month).
                                    @endif
                                </p>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                                <textarea
                                    name="notes"
                                    id="notes"
                                    rows="4"
                                    class="block w-full resize-y rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-700 dark:bg-transparent dark:text-white"
                                >{{ old('notes') }}</textarea>
                            </div>

                            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-6 dark:border-gray-800">
                                <a
                                    href="{{ route('users.index') }}"
                                    class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-white dark:hover:bg-white/5"
                                >
                                    Cancel
                                </a>
                                <button
                                    type="submit"
                                    class="rounded-md bg-gray-900 px-6 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100"
                                >
                                    Assign
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
