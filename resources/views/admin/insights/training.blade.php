@extends('admin.insights._layout', ['tab' => 'training'])

@php
    $hours = fn (float $h) => rtrim(rtrim(number_format($h, 1), '0'), '.').' h';

    $retention = $insights['retention'];
    $headlineWeek = $retention['weeks'][\App\Services\Admin\Insights::HEADLINE_RETENTION_WEEK];
    $retentionChart = [
        'categories' => array_map(fn (int $week, array $row) => ["Week {$week}", $row['reached'] ? 'n = '.number_format($row['eligible']) : 'not yet'], array_keys($retention['weeks']), $retention['weeks']),
        'series' => [['name' => 'Trained that week', 'data' => array_map(fn (array $row) => $row['reached'] ? $row['share'] : null, array_values($retention['weeks']))]],
        'suffix' => '%',
        'max' => 100,
    ];

    $firstWorkout = $insights['first_workout'];
    $median = $firstWorkout['median_hours'];
    $previous = $firstWorkout['previous_median_hours'];
    $change = $median !== null && $previous !== null ? round($median - $previous, 1) : null;
    $firstWorkoutChart = [
        'categories' => array_column($firstWorkout['buckets'], 'label'),
        'series' => [['name' => 'Signups', 'data' => array_column($firstWorkout['buckets'], 'users')]],
        'horizontal' => true,
        'distributed' => true,
        // "Not yet" is the last bucket: recessive, the rest in the series colour.
        'colors' => [...array_fill(0, count($firstWorkout['buckets']) - 1, 's1'), 'muted'],
    ];

    $generator = $insights['generator'];
    $generated = $generator['generated'];
    $other = $generator['other'];
    $generatorChart = [
        'categories' => ['Completed', 'Swapped', 'Cancelled'],
        'series' => [
            ['name' => 'Generated ('.number_format($generated['sessions']).')', 'data' => [$generated['completed'], $generated['swapped'], $generated['cancelled']]],
            ['name' => 'Other ('.number_format($other['sessions']).')', 'data' => [$other['completed'], $other['swapped'], $other['cancelled']]],
        ],
        'suffix' => '%',
        'max' => 100,
    ];

    $skipped = $insights['skipped'];
    $minIncluded = number_format(\App\Services\Admin\Insights::SKIPPED_MIN_INCLUDED);
@endphp

@section('tab')
    <div class="grid gap-5 lg:grid-cols-2">
        <x-admin.insight-card
            question="Do people keep training?"
            :headline="$headlineWeek['reached'] ? $headlineWeek['share'].'%' : '—'"
            :sentence="$headlineWeek['reached']
                ? 'still log a Completed Session in week '.\App\Services\Admin\Insights::HEADLINE_RETENTION_WEEK
                : 'no signup in the last '.$days.' days has reached week '.\App\Services\Admin\Insights::HEADLINE_RETENTION_WEEK.' yet'"
            :footnote="number_format($retention['signups']).' signups in the last '.$days.' days. Week N is days 7(N−1) to 7N−1 after signup; each week counts only signups old enough to have finished it (n).'"
        >
            <div class="h-56" x-data="insightChart(@js($retentionChart))"></div>
        </x-admin.insight-card>

        <x-admin.insight-card
            question="How fast do they start?"
            :headline="$median !== null ? $hours($median) : '—'"
            :sentence="$median !== null ? 'median from signup to first Completed Session' : 'no signup in the last '.$days.' days has trained yet'"
            :footnote="'Signups in the last '.$days.' days, compared with signups in the '.$days.' days before. “Not yet” = no Completed Session so far.'"
        >
            <x-slot:comparison>
                @if ($change !== null)
                    <span @class([
                        'text-theme-xs font-medium',
                        'text-success-600 dark:text-success-500' => $change < 0,
                        'text-error-600 dark:text-error-500' => $change > 0,
                        'text-gray-500 dark:text-gray-400' => $change == 0,
                    ])>{{ $change > 0 ? '+' : ($change < 0 ? '−' : '±') }}{{ $hours(abs($change)) }} vs the {{ $days }} days before</span>
                @elseif ($median !== null)
                    <span class="text-theme-xs text-gray-500 dark:text-gray-400">no signups trained in the {{ $days }} days before</span>
                @endif
            </x-slot:comparison>

            <div class="h-56" x-data="insightChart(@js($firstWorkoutChart))"></div>
        </x-admin.insight-card>

        <x-admin.insight-card
            question="Are generated workouts any good?"
            :headline="$generated['sessions'] > 0 ? $generated['completed'].'%' : '—'"
            :sentence="$generated['sessions'] > 0
                ? 'of generated sessions get completed'.($other['sessions'] > 0 ? ' — '.$other['completed'].'% for the rest' : '')
                : 'no generated session was created in the last '.$days.' days'"
            :footnote="'Sessions created in the last '.$days.' days (count in brackets). Generated = made by the workout generator. Swapped = regenerated into another session; Cancelled = cancelled without a replacement. Drafts and sessions in progress count in the total only.'"
        >
            <div class="h-56" x-data="insightChart(@js($generatorChart))"></div>
        </x-admin.insight-card>

        <x-admin.insight-card
            question="Which exercises get skipped?"
            :headline="$skipped ? $skipped[0]['rate'].'%' : '—'"
            :sentence="$skipped
                ? 'of '.$skipped[0]['name'].' entries have no logged set'
                : 'No exercise was included '.$minIncluded.' times in Completed Sessions in the last '.$days.' days'"
            :footnote="'Per exercise, the share of its entries in Completed Sessions completed in the last '.$days.' days with no logged set (skipped of included). Only exercises included at least '.$minIncluded.' times; top '.\App\Services\Admin\Insights::SKIPPED_TOP.' by rate.'"
        >
            @if ($skipped)
                <ol class="space-y-2.5">
                    @foreach ($skipped as $exercise)
                        <li class="flex items-center gap-3 text-sm">
                            <a href="{{ route('exercises.show', $exercise['exercise_id']) }}"
                                class="w-36 shrink-0 truncate font-medium text-gray-700 hover:text-brand-600 sm:w-44 dark:text-gray-300 dark:hover:text-brand-400"
                                title="{{ $exercise['name'] }}">{{ $exercise['name'] }}</a>
                            <div class="h-3 min-w-0 flex-1 rounded-sm bg-gray-100 dark:bg-gray-800">
                                <div class="h-3 rounded-sm bg-orange-500 dark:bg-orange-600" style="width: {{ $exercise['rate'] }}%"></div>
                            </div>
                            <span class="w-28 shrink-0 text-right text-theme-xs tabular-nums text-gray-600 dark:text-gray-400">
                                {{ $exercise['rate'] }}% · {{ number_format($exercise['skipped']) }} of {{ number_format($exercise['included']) }}
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-admin.insight-card>
    </div>
@endsection
