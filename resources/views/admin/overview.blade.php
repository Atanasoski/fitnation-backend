@extends('layouts.app')

@section('title', 'Overview')

@php
    $kpis = [
        ['label' => 'Users', 'kpi' => $overview['kpis']['users'], 'hint' => 'app users in total', 'url' => route('admin.users.index')],
        ['label' => 'Signups', 'kpi' => $overview['kpis']['signups'], 'hint' => 'last 7 days', 'url' => route('admin.users.index', ['signed_up_days' => 7])],
        ['label' => 'Active', 'kpi' => $overview['kpis']['active'], 'hint' => 'Completed Session in the last 7 days', 'url' => route('admin.users.index', ['activity' => 'active'])],
        ['label' => 'Completed Sessions', 'kpi' => $overview['kpis']['completed_sessions'], 'hint' => 'last 7 days', 'url' => null],
    ];

    $attention = $overview['attention'];
    $attentionRows = [
        ['label' => 'Failed jobs', 'value' => $attention['failed_jobs'], 'detail' => 'Queue jobs that ran out of attempts', 'url' => route('admin.system')],
        ['label' => 'Failed webhooks', 'value' => $attention['failed_webhooks'], 'detail' => 'RevenueCat calls waiting for a replay', 'url' => route('admin.system')],
        ['label' => 'Unfinished Accounts', 'value' => $attention['unfinished_accounts'], 'detail' => 'Unverified, or verified and not onboarded', 'url' => route('admin.users.index', ['activity' => 'unfinished'])],
        ['label' => 'Stuck sessions', 'value' => $attention['stuck_sessions'], 'detail' => 'Users with a session active for over 24 hours', 'url' => route('admin.users.index', ['stuck' => 1])],
        ['label' => 'Sponsorships expiring', 'value' => $attention['expiring_sponsorships'], 'detail' => 'Sponsoring Partners running out within 30 days', 'url' => route('admin.partners.index', ['expiring' => 1])],
    ];

    $funnel = $overview['funnel'];
    $funnelStages = [
        ['Signed up', $funnel['signed_up']],
        ['Verified', $funnel['verified']],
        ['Onboarded', $funnel['onboarded']],
        ['First Completed Session', $funnel['first_completed_session']],
        ['Trained in week two', $funnel['trained_in_week_two']],
    ];
    $percent = fn (int $n) => $funnel['signed_up'] > 0 ? (int) round($n / $funnel['signed_up'] * 100) : 0;
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Overview" />

    <div class="space-y-5">
        {{-- KPIs: the last 7 days against the 7 days before --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ($kpis as $card)
                @php($delta = $card['kpi']['delta'])
                {!! $card['url'] ? '<a href="'.e($card['url']).'"' : '<div' !!}
                    class="block rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03] {{ $card['url'] ? 'transition-colors hover:border-brand-500' : '' }}">
                    <div class="text-theme-xs text-gray-500 dark:text-gray-400">{{ $card['label'] }}</div>
                    <div class="mt-1 flex items-baseline gap-2">
                        <span class="font-display text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($card['kpi']['current']) }}</span>
                        <span @class([
                            'text-theme-xs font-medium',
                            'text-success-600' => $delta > 0,
                            'text-error-600' => $delta < 0,
                            'text-gray-400' => $delta === 0,
                        ])>{{ $delta > 0 ? '+' : '' }}{{ number_format($delta) }}</span>
                    </div>
                    <div class="text-theme-xs text-gray-400">{{ $card['hint'] }} · vs {{ number_format($card['kpi']['previous']) }} the 7 days before</div>
                {!! $card['url'] ? '</a>' : '</div>' !!}
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            {{-- Needs attention --}}
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03] lg:col-span-2">
                <h2 class="font-display text-sm font-semibold text-gray-900 dark:text-white">Needs attention</h2>
                <ul class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($attentionRows as $row)
                        <li>
                            <a href="{{ $row['url'] }}" class="flex items-center gap-3 rounded-lg px-2 py-2.5 hover:bg-gray-50 dark:hover:bg-white/5">
                                <span @class([
                                    'w-12 rounded-md py-0.5 text-center text-sm font-semibold tabular-nums',
                                    'bg-orange-50 text-orange-700 dark:bg-orange-500/15 dark:text-orange-400' => $row['value'] > 0,
                                    'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => $row['value'] === 0,
                                ])>{{ number_format($row['value']) }}</span>
                                <span class="flex-1">
                                    <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $row['label'] }}</span>
                                    <span class="block text-theme-xs text-gray-500 dark:text-gray-400">{{ $row['detail'] }}</span>
                                </span>
                                <span class="text-gray-400" aria-hidden="true">→</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- Paywall --}}
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <h2 class="font-display text-sm font-semibold text-gray-900 dark:text-white">Paywall</h2>
                <div class="mt-3 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <span @class(['h-2.5 w-2.5 rounded-full', 'bg-success-500' => $overview['paywall']['enforced'], 'bg-gray-400' => ! $overview['paywall']['enforced']])></span>
                    <span>Subscriptions enforced: <b>{{ $overview['paywall']['enforced'] ? 'On' : 'Off' }}</b></span>
                </div>
                <a href="{{ route('admin.users.index', ['access' => 'none']) }}" class="mt-4 block rounded-lg bg-orange-50 p-3 transition-colors hover:bg-orange-100 dark:bg-orange-500/10 dark:hover:bg-orange-500/20">
                    <div class="font-display text-2xl font-semibold text-orange-700 dark:text-orange-400">{{ number_format($overview['paywall']['none']) }}</div>
                    <div class="text-theme-xs text-orange-700 dark:text-orange-400">users with Access Source None — who the paywall stops</div>
                </a>
            </section>
        </div>

        {{-- Activation funnel --}}
        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <h2 class="font-display text-sm font-semibold text-gray-900 dark:text-white">
                Activation funnel
                <span class="font-sans text-theme-xs font-normal text-gray-400">· signups in the last {{ \App\Services\Admin\Overview::FUNNEL_DAYS }} days</span>
            </h2>
            <div class="mt-3 space-y-2">
                @foreach ($funnelStages as [$label, $count])
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-48 shrink-0 text-gray-600 dark:text-gray-400">{{ $label }}</span>
                        <div class="h-5 flex-1 rounded bg-gray-100 dark:bg-gray-800">
                            <div class="h-5 rounded bg-brand-500" style="width: {{ $percent($count) }}%"></div>
                        </div>
                        <span class="w-24 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($count) }} · {{ $percent($count) }}%</span>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-theme-xs text-gray-400">Each stage is counted on its own. Week two is days 7–13 after signup.</p>
        </section>
    </div>
@endsection
