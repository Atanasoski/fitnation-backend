@extends('layouts.app')

@section('title', 'Edit Plan - ' . $plan->name)

@section('content')
    <x-common.page-breadcrumb :pageTitle="'Edit Plan'" :items="[['label' => 'Users', 'url' => route('users.index')], ['label' => $plan->user->name, 'url' => route('users.show', $plan->user)], ['label' => $plan->name, 'url' => route('plans.show', $plan)]]" />

    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-error-200 bg-error-50 p-4 dark:border-error-800 dark:bg-error-900/20">
            <div class="mb-2 text-sm font-semibold text-error-800 dark:text-error-400">
                There were some errors with your submission:
            </div>
            <ul class="list-inside list-disc space-y-1 text-sm text-error-700 dark:text-error-300">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-common.component-card title="Edit Plan Information" :desc="'Update plan details for ' . $plan->user->name">
        @include('plans._form', [
            'plan' => $plan,
            'action' => route('plans.update', $plan),
            'method' => 'PUT',
            'context' => 'user',
            'user' => $plan->user
        ])
    </x-common.component-card>
@endsection
