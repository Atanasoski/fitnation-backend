@extends('layouts.app')

@section('title', 'System')

@section('content')
    <x-common.page-breadcrumb pageTitle="System" />

    @include('admin._placeholder', [
        'heading' => 'System is on its way',
        'body' => 'Failed queue jobs and failed RevenueCat webhooks, with retry and replay, will live here.',
    ])
@endsection
