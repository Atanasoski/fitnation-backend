{{-- The Insights page shell: the Training | Revenue tab strip, the range control on the Training tab, and the chart colour swatches. --}}
@extends('layouts.app')

@section('title', 'Insights')

@section('content')
    <x-common.page-breadcrumb pageTitle="Insights" />

    @php
        $tabs = [
            'training' => ['Training', route('admin.insights')],
            'revenue' => ['Revenue', route('admin.insights.revenue')],
        ];
    @endphp

    <div class="mb-5 flex flex-wrap items-end justify-between gap-3 border-b border-gray-200 dark:border-gray-800">
        <nav class="-mb-px flex gap-5" aria-label="Insights tabs">
            @foreach ($tabs as $key => [$label, $url])
                <a href="{{ $url }}" @if ($tab === $key) aria-current="page" @endif
                    @class([
                        'border-b-2 px-1 pb-2.5 text-sm font-medium',
                        'border-brand-500 text-brand-600 dark:text-brand-400' => $tab === $key,
                        'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $tab !== $key,
                    ])>{{ $label }}</a>
            @endforeach
        </nav>

        @isset($days)
            <div class="mb-2 inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-gray-900" role="group" aria-label="Range">
                @foreach (\App\Services\Admin\Insights::RANGES as $range)
                    <a href="{{ route('admin.insights', ['range' => $range]) }}" aria-current="{{ $range === $days ? 'true' : 'false' }}"
                        @class([
                            'rounded-md px-3 py-1 text-sm font-medium',
                            'bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white' => $range === $days,
                            'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $range !== $days,
                        ])>{{ $range }} days</a>
                @endforeach
            </div>
        @endisset
    </div>

    {{-- Chart colours, read by the insightChart Alpine component so no hex lives in the page.
         Validated pairs: light brand-500 / orange-500 / blue-light-700, dark brand-600 / orange-600 / blue-light-600. --}}
    <div aria-hidden="true" class="hidden">
        <i data-chart-swatch="s1" class="text-brand-500 dark:text-brand-600"></i>
        <i data-chart-swatch="s2" class="text-orange-500 dark:text-orange-600"></i>
        <i data-chart-swatch="s3" class="text-blue-light-700 dark:text-blue-light-600"></i>
        <i data-chart-swatch="muted" class="text-gray-300 dark:text-gray-700"></i>
        <i data-chart-swatch="ink" class="text-gray-500 dark:text-gray-400"></i>
        <i data-chart-swatch="label" class="text-gray-700 dark:text-gray-200"></i>
        <i data-chart-swatch="grid" class="text-gray-100 dark:text-gray-800"></i>
    </div>

    @yield('tab')
@endsection
