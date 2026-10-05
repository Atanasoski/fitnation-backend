@extends('layouts.app')

@section('title', 'Insights')

@section('content')
    <x-common.page-breadcrumb pageTitle="Insights" />

    @include('admin._placeholder', [
        'heading' => 'Coming soon',
        'body' => 'Retention cohorts, generator quality, nudge effectiveness and demographics arrive in v2.',
    ])
@endsection
