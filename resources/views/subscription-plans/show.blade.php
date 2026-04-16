@extends('layouts.app')

@section('title', $subscriptionPlan->name)

@section('content')
    <x-common.page-breadcrumb :pageTitle="$subscriptionPlan->name" :items="[['label' => 'Subscription plans', 'url' => route('subscription-plans.index')]]" />

    <x-common.component-card title="{{ $subscriptionPlan->name }}" desc="Plan overview">
        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Price</dt>
                <dd class="text-sm text-gray-900 dark:text-white">${{ number_format((float) $subscriptionPlan->price, 2) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Billing cycle</dt>
                <dd class="text-sm text-gray-900 dark:text-white">{{ str($subscriptionPlan->billing_cycle?->value)->replace('_', ' ')->title() }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Subscriptions</dt>
                <dd class="text-sm text-gray-900 dark:text-white">{{ $subscriptionPlan->subscriptions_count }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Active</dt>
                <dd class="text-sm text-gray-900 dark:text-white">{{ $subscriptionPlan->is_active ? 'Yes' : 'No' }}</dd>
            </div>
            @if ($subscriptionPlan->description)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Description</dt>
                    <dd class="text-sm text-gray-900 dark:text-white">{{ $subscriptionPlan->description }}</dd>
                </div>
            @endif
        </dl>
        <div class="mt-6">
            <a href="{{ route('subscription-plans.edit', $subscriptionPlan) }}" class="text-sm font-medium text-gray-900 underline dark:text-white">Edit plan</a>
        </div>
    </x-common.component-card>
@endsection
