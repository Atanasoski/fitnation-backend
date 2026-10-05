@extends('layouts.app')

@section('title', 'Overview')

@section('content')
    <x-common.page-breadcrumb pageTitle="Overview" />

    @include('admin._placeholder', [
        'heading' => 'Overview is on its way',
        'body' => 'KPIs, Needs attention, the Paywall card and the activation funnel will live here.',
    ])
@endsection
