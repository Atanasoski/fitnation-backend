@extends('emails.layout')

@php($partnerName = $user->partner?->name ?? config('app.name'))

@section('title', 'Verify your email')

@section('header', 'Welcome to '.$partnerName)

@section('content')
    <p class="greeting">Hello, {{ $user->name }}!</p>

    <div class="message">
        <p>You recently created an account at <strong>{{ $partnerName }}</strong>.</p>
        <p>Please verify your email address to activate your account and continue your fitness journey.</p>
    </div>

    <div style="text-align: center;">
        <a href="{{ $verificationUrl }}" class="cta-button" style="color: #ffffff !important;">
            Verify Email Address
        </a>
    </div>

    <div class="secondary-info">
        <p style="margin: 0; font-size: 14px;">
            This verification link expires automatically for security reasons.
        </p>
    </div>

    <p style="margin-top: 30px; color: #6b7280; font-size: 14px;">
        If you did not create this account, you can safely ignore this email.
    </p>
@endsection
