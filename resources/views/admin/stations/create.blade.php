@extends('layouts.app')

@section('title', 'New station — '.$partner->name)

@section('content')
    <div class="space-y-4">
        <a href="{{ route('admin.partners.show', $partner) }}" class="text-sm text-brand-600 hover:underline dark:text-brand-400">← {{ $partner->name }}</a>

        <h1 class="font-display text-xl font-semibold text-gray-900 dark:text-white">New station</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">A spot on {{ $partner->name }}'s gym floor. Its QR short code is generated on creation and never changes.</p>

        <form method="POST" action="{{ route('admin.stations.store', $partner) }}">
            @csrf
            @include('admin.stations._form')
        </form>
    </div>
@endsection
