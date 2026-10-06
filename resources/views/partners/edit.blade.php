@extends('layouts.app')

@section('title', 'Edit '.$partner->name)

@php
    $back = auth()->user()->hasRole('admin') ? route('admin.partners.show', $partner) : route('partners.show', $partner);
@endphp

@section('content')
    <div class="space-y-4">
        <a href="{{ $back }}" class="text-sm text-brand-600 hover:underline dark:text-brand-400">← {{ $partner->name }}</a>
        <h1 class="font-display text-xl font-semibold text-gray-900 dark:text-white">Edit partner</h1>

        @include('partners._form', [
            'action' => route('partners.update', $partner),
            'method' => 'PUT',
            'submitLabel' => 'Save',
            'cancelUrl' => $back,
        ])
    </div>
@endsection
