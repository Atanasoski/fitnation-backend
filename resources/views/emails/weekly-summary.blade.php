@extends('emails.layout')

@php($partnerName = $user->partner?->name ?? config('app.name'))

@section('title', $subject)

@section('header', $partnerName)

@section('content')
    <p class="greeting">Hello, {{ $user->name }}!</p>

    <div class="message">
        <p style="margin: 0 0 12px 0;"><strong style="color: #111827; font-size: 20px;">{{ ucfirst($workouts) }}</strong> &mdash; {{ $workoutsDelta }}</p>
        @if($volume)
            <p style="margin: 0 0 12px 0;"><strong style="color: #111827; font-size: 20px;">{{ $volume }}</strong> &mdash; {{ $volumeDelta }}</p>
        @endif
        <p style="margin: 0 0 12px 0;"><strong style="color: #111827; font-size: 20px;">{{ $time }}</strong></p>
        <p style="margin: 20px 0 0 0;">{{ $trendLine }}</p>

        @if($charts)
            @include('emails.partials.column-chart', ['title' => $chartWeeksTitle, 'bars' => $charts['weeks'], 'accent' => $accent])
            @include('emails.partials.column-chart', ['title' => $chartDaysTitle, 'bars' => $charts['days'], 'accent' => $accent])
        @endif
    </div>

    <div style="text-align: center;">
        <a href="{{ $appUrl }}" class="cta-button" style="color: #ffffff !important;">
            {{ $button }}
        </a>
    </div>

    <p style="margin-top: 30px; color: #6b7280; font-size: 14px; text-align: center;">
        <a href="{{ $unsubscribeUrl }}" style="color: #6b7280;">{{ $unsubscribe }}</a>
    </p>
@endsection
