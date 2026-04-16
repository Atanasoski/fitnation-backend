@extends('layouts.app')

@section('title', 'Create subscription plan')

@section('content')
    <x-common.page-breadcrumb pageTitle="Create subscription plan" :items="[['label' => 'Subscription plans', 'url' => route('subscription-plans.index')]]" />

    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
            <ul class="list-inside list-disc text-sm text-red-700 dark:text-red-300">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-common.component-card title="Plan details" desc="Add a plan your gym can assign to members">
        <form action="{{ route('subscription-plans.store') }}" method="POST" class="space-y-6">
            @csrf
            @include('subscription-plans._form')
            <div class="flex justify-end gap-3 border-t border-gray-100 pt-6 dark:border-gray-800">
                <a href="{{ route('subscription-plans.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-300">Cancel</a>
                <button type="submit" class="inline-flex items-center rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-medium text-white dark:bg-white dark:text-gray-900">Create</button>
            </div>
        </form>
    </x-common.component-card>
@endsection
