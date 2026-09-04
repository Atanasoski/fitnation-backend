@extends('layouts.app')

@section('title', 'Create Plan for ' . $user->name)

@section('content')
    <x-common.page-breadcrumb :pageTitle="'Create Plan for ' . $user->name" :items="[['label' => 'Users', 'url' => route('users.index')], ['label' => $user->name, 'url' => route('users.show', $user)]]" />

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

    <x-common.component-card title="Plan Information" :desc="'Create a new workout plan for ' . $user->name">
        @include('plans._form', [
            'action' => route('plans.store', $user),
            'method' => 'POST',
            'context' => 'user',
            'user' => $user
        ])
    </x-common.component-card>
@endsection
