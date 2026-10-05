@extends('layouts.app')

@section('title', 'Create Partner')

@section('content')
    <div class="space-y-4">
        <a href="{{ route('admin.partners.index') }}" class="text-sm text-brand-600 hover:underline dark:text-brand-400">← Partners</a>
        <h1 class="font-display text-xl font-semibold text-gray-900 dark:text-white">Create New Partner</h1>

        @include('partners._form', [
            'partner' => null,
            'action' => route('partners.store'),
            'method' => 'POST',
            'submitLabel' => 'Create partner',
            'cancelUrl' => route('admin.partners.index'),
        ])
    </div>
@endsection
