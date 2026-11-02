@extends('layouts.app')

@section('title', $station->name.' — '.$partner->name)

@section('content')
    <div class="space-y-4">
        <a href="{{ route('admin.partners.show', $partner) }}" class="text-sm text-brand-600 hover:underline dark:text-brand-400">← {{ $partner->name }}</a>

        <div class="flex flex-wrap items-center gap-3">
            <h1 class="font-display text-xl font-semibold text-gray-900 dark:text-white">{{ $station->name }}</h1>
            <span class="rounded-full bg-brand-50 px-2.5 py-0.5 font-mono text-theme-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">{{ $station->short_code }}</span>
            <div class="ml-auto flex items-center gap-2">
                <a href="{{ route('admin.stations.qr', [$partner, $station, 'svg']) }}" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Download QR (SVG)</a>
                <a href="{{ route('admin.stations.qr', [$partner, $station, 'png']) }}" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Download QR (PNG)</a>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.stations.update', [$partner, $station]) }}">
            @csrf
            @method('PUT')
            @include('admin.stations._form')
        </form>
    </div>
@endsection
