@extends('emails.layout')

@php($partnerName = $user->partner?->name ?? config('app.name'))

@section('title', $subject)

@section('header', $partnerName)

@section('content')
    <p class="greeting">Hello, {{ $user->name }}!</p>

    <div class="message">
        <p>{{ $lead }}</p>
        <p>{{ $follow }}</p>
    </div>

    <div style="text-align: center;">
        <a href="{{ $primaryUrl }}" class="cta-button" style="color: #ffffff !important;">
            {{ $button }}
        </a>
    </div>

    @if($secondary)
        <div class="secondary-info">
            <p style="margin: 0; font-size: 14px;">
                {{ $secondary }}: <a href="{{ $appUrl }}">{{ $appUrl }}</a>
            </p>
        </div>
    @endif

    <p style="margin-top: 30px; color: #6b7280; font-size: 14px;">
        If you did not create this account, you can safely ignore this email.
    </p>
@endsection
