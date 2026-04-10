@extends('layouts.app')

@section('title', 'Gym Dashboard')

@section('content')
<div class="p-6">
    <div class="mb-6">
        <div class="flex items-center mb-3">
            @if($partner->identity && $partner->identity->logo_url)
                <img src="{{ $partner->identity->logo_url }}" alt="{{ $partner->name }}" class="w-16 h-16 rounded mr-4 object-cover">
            @endif
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $partner->name }} Dashboard
                </h2>
                <p class="text-gray-600 dark:text-gray-400">Welcome back, {{ Auth::user()->name }} - Gym Manager</p>
            </div>
        </div>
    </div>
</div>
@endsection
