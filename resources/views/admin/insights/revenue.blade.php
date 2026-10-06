@extends('admin.insights._layout', ['tab' => 'revenue'])

@php
    $usd = fn (float $amount) => '$'.number_format($amount, 2);
    $plans = \App\Enums\SubscriptionPlan::cases();
    $stores = \App\Enums\SubscriptionStore::cases();

    $sourcesChart = [
        'categories' => array_map(fn ($store) => $store->label(), $stores),
        'series' => array_map(fn ($plan) => [
            'name' => $plan === \App\Enums\SubscriptionPlan::Yearly ? 'Yearly ÷ 12' : $plan->label(),
            'data' => array_map(fn ($store) => $revenue['by_store_and_plan'][$store->value][$plan->value]['usd'], $stores),
        ], $plans),
        'horizontal' => true,
        'stacked' => true,
        'prefix' => '$',
    ];

    $tile = 'rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]';
    $card = 'flex flex-col rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]';
    $label = 'text-theme-xs text-gray-500 dark:text-gray-400';
    $value = 'mt-1 font-display text-2xl font-semibold text-gray-900 dark:text-white';
    $note = 'mt-1 text-theme-xs text-gray-500 dark:text-gray-400';
    $footnote = 'mt-3 border-t border-gray-100 pt-2 text-theme-xs text-gray-500 dark:border-gray-800 dark:text-gray-400';
@endphp

@section('tab')
    <div class="space-y-5">
        <p class="text-right text-theme-xs text-gray-500 dark:text-gray-400">
            Current state of production subscriptions · Sandbox excluded ({{ number_format($revenue['sandbox_excluded']) }})
        </p>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="{{ $tile }}">
                <div class="{{ $label }}">Expected Monthly Revenue</div>
                <div class="{{ $value }}">{{ $usd($revenue['expected_monthly_usd']) }}</div>
                <div class="{{ $note }}">USD, RevenueCat’s USD conversion · yearly counted as ÷ 12</div>
            </div>
            <div class="{{ $tile }}">
                <div class="{{ $label }}">Paying</div>
                <div class="{{ $value }}">{{ number_format($revenue['paying']['total']) }}</div>
                <div class="{{ $note }}">{{ number_format($revenue['paying']['monthly']) }} monthly · {{ number_format($revenue['paying']['yearly']) }} yearly</div>
            </div>
            <div class="{{ $tile }}">
                <div class="{{ $label }}">In trial</div>
                <div class="{{ $value }}">{{ number_format($revenue['trials']) }}</div>
                <div class="{{ $note }}">7-day trial, both plans</div>
            </div>
            <a href="{{ route('admin.users.index', ['access' => \App\Enums\AccessSource::BillingIssue->value]) }}"
                class="{{ $tile }} block hover:border-orange-500 dark:hover:border-orange-500">
                <div class="{{ $label }}">Billing issue</div>
                <div class="mt-1 font-display text-2xl font-semibold text-orange-600 dark:text-orange-400">{{ number_format($revenue['billing_issue']) }}</div>
                <div class="{{ $note }}">store retrying the charge · see the users →</div>
            </a>
        </div>

        <div class="grid gap-5 lg:grid-cols-[1fr_20rem]">
            <section class="{{ $card }} min-w-0">
                <h2 class="font-display text-base font-semibold text-gray-800 dark:text-white/90">Where the money comes from</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Expected Monthly Revenue by store and plan, USD per month</p>
                <div class="mt-3 h-56 flex-1" x-data="insightChart(@js($sourcesChart))"></div>
                <p class="{{ $footnote }}">
                    Paying = the subscription still grants access and is not in a trial, so cancelled-but-paid-until and billing-issue subscriptions count.
                    Price is RevenueCat’s USD figure for the last transaction, not what the user paid in their own currency.
                    @if ($revenue['unknown_price'] > 0)
                        {{ $revenue['unknown_price'] }} paying {{ \Illuminate\Support\Str::plural('subscription', $revenue['unknown_price']) }} {{ $revenue['unknown_price'] === 1 ? 'has' : 'have' }} no price: counted as paying, not as revenue.
                    @endif
                </p>
            </section>

            <section class="{{ $card }}">
                <h2 class="font-display text-base font-semibold text-gray-800 dark:text-white/90">Trial → paid</h2>
                <div class="flex-1">
                    @foreach ($plans as $plan)
                        @php($conversion = $revenue['conversion'][$plan->value])
                        <div class="mt-3">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-700 dark:text-gray-300">{{ $plan->label() }}</span>
                                <span class="font-semibold tabular-nums text-gray-900 dark:text-white">{{ $conversion['rate'] !== null ? $conversion['rate'].'%' : '—' }}</span>
                            </div>
                            <x-admin.share-bar class="mt-1" :percent="$conversion['rate'] ?? 0" />
                            <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">
                                {{ number_format($conversion['converted']) }} of {{ number_format($conversion['converted'] + $conversion['lapsed_trial']) }} finished trials
                                · {{ number_format($conversion['still_in_trial']) }} still in trial
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="{{ $footnote }}">
                    Converted = no longer on a trial period, whatever its status now. Assumes every first purchase starts with the 7-day trial,
                    so a returning subscriber who got no trial counts as converted. Users still in a trial are left out.
                </p>
            </section>
        </div>

        <section class="rounded-xl border border-dashed border-gray-300 p-5 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-orange-500/15 px-2 py-0.5 text-theme-xs font-medium text-orange-600 dark:text-orange-400">Current state only</span>
                <b class="text-gray-700 dark:text-gray-300">Not in this version</b>
            </div>
            <p class="mt-2">
                Churn per month, billing issues over time, paid vs sponsored users over time and the paywall funnel all need history.
                Subscription rows keep only their latest state, and the panel has no scheduled jobs (Laravel Cloud scales to zero), so none of these can be counted yet.
                Revenue per purchase currency is missing too: only RevenueCat’s USD price is stored.
            </p>
        </section>
    </div>
@endsection
