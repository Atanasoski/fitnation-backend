@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <x-common.page-breadcrumb pageTitle="Users" />

    @include('admin._placeholder', [
        'heading' => 'The Users list is on its way',
        'body' => 'Every user on the platform, with Activity Status and Access Source, will live here.',
    ])
@endsection
