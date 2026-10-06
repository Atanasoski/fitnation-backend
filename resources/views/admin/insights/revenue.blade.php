@extends('admin.insights._layout', ['tab' => 'revenue'])

@section('tab')
    @include('admin._placeholder', [
        'heading' => 'Revenue',
        'body' => 'Expected Monthly Revenue, subscribers and trial conversion arrive here next.',
    ])
@endsection
