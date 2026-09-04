@extends('layouts.app')

@section('title', 'Create Program')

@section('content')
    <x-common.page-breadcrumb :pageTitle="'Create Program'" :items="[['label' => 'Programs', 'url' => route('partner.programs.index')]]" />

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

    <x-common.component-card title="Program Information" desc="Create a new program available to all gym members">
        @include('plans._form', [
            'action' => route('partner.programs.store'),
            'method' => 'POST',
            'context' => 'library'
        ])
    </x-common.component-card>
@endsection
