@extends('emails.layout')

@php($partnerName = $user->partner?->name ?? config('app.name'))

@section('title', 'Unsubscribed')

@section('header', $partnerName)

@section('content')
    <p class="message" style="text-align: center;">
        You won't get the weekly summary email any more &mdash; your training is still in the app.
    </p>

    <div style="text-align: center;">
        <a href="{{ $appUrl }}" class="cta-button" style="color: #ffffff !important;">
            Open the app
        </a>
    </div>
@endsection
