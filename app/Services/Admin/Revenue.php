<?php

namespace App\Services\Admin;

use App\Enums\AccessSource;
use App\Enums\SubscriptionPeriodType;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStore;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The super-admin Revenue tab: money and subscribers from the current state
 * of `subscriptions` — rows hold only their latest state, so there are no
 * trends here. Production rows of app users (User::appUsers()) only.
 *
 * - sandbox_excluded — every sandbox row, whoever's (testers are often
 *   staff): the test purchases kept out of every other number.
 * - paying — subscriptions that grant access (Subscription::active(), so
 *   cancelled-but-paid-until, billing issue and paused still count) and are
 *   not in a trial, by plan (SubscriptionPlan, read from the product id).
 * - trials — subscriptions in an active trial.
 * - billing_issue — users with Access Source Billing issue, through
 *   AccessSources::constrain, so it equals the Users list it links to.
 * - expected_monthly_usd — Expected Monthly Revenue (CONTEXT.md): Σ price of
 *   paying monthly subscriptions plus Σ price ÷ 12 of yearly ones.
 *   `price` is RevenueCat's USD price of the last transaction; it is NOT in
 *   `currency`, so never pair the two.
 * - by_store_and_plan — the same, per store and plan, with subscriber counts.
 * - conversion — trial → paid per plan: converted = rows no longer on a
 *   trial period (any status), lapsed_trial = trial rows that no longer
 *   grant access, still_in_trial left out of the rate. Assumes every first
 *   purchase starts with the 7-day trial, so a returning subscriber who was
 *   not trial-eligible reads as converted.
 * - unknown_price — paying subscriptions with no price: counted in paying,
 *   contributing nothing to revenue.
 *
 * Live queries, cached for CACHE_SECONDS — no snapshot tables or scheduled jobs.
 */
final class Revenue
{
    public const CACHE_KEY = 'admin.revenue';

    public const CACHE_SECONDS = 600;

    /**
     * @return array{
     *     sandbox_excluded: int,
     *     paying: array{total: int, monthly: int, yearly: int},
     *     trials: int,
     *     billing_issue: int,
     *     expected_monthly_usd: float,
     *     by_store_and_plan: array<'app_store'|'play_store', array<'monthly'|'yearly', array{subscribers: int, usd: float}>>,
     *     conversion: array<'monthly'|'yearly', array{converted: int, lapsed_trial: int, still_in_trial: int, rate: ?int}>,
     *     unknown_price: int,
     * }
     */
    public static function summary(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => self::compute());
    }

    private static function compute(): array
    {
        $production = fn () => Subscription::query()
            ->where('environment', 'production')
            ->whereHas('user', fn (Builder $users) => $users->appUsers());
        $paying = $production()->active()->where('period_type', '!=', SubscriptionPeriodType::Trial)->get(['product_id', 'store', 'price']);

        return [
            'sandbox_excluded' => Subscription::query()->where('environment', 'sandbox')->count(),
            'paying' => [
                'total' => $paying->count(),
                'monthly' => self::onPlan($paying, SubscriptionPlan::Monthly)->count(),
                'yearly' => self::onPlan($paying, SubscriptionPlan::Yearly)->count(),
            ],
            'trials' => $production()->active()->where('period_type', SubscriptionPeriodType::Trial)->count(),
            'billing_issue' => AccessSources::constrain(User::query()->appUsers(), AccessSource::BillingIssue)->count(),
            'expected_monthly_usd' => self::monthlyUsd($paying),
            'by_store_and_plan' => collect(SubscriptionStore::cases())->mapWithKeys(fn (SubscriptionStore $store) => [
                $store->value => collect(SubscriptionPlan::cases())->mapWithKeys(fn (SubscriptionPlan $plan) => [
                    $plan->value => [
                        'subscribers' => ($cell = self::onPlan($paying, $plan)->where('store', $store))->count(),
                        'usd' => self::monthlyUsd($cell),
                    ],
                ])->all(),
            ])->all(),
            'conversion' => self::conversion($production()->get(['product_id', 'status', 'period_type', 'expires_at'])),
            'unknown_price' => $paying->whereNull('price')->count(),
        ];
    }

    /**
     * @param  Collection<int, Subscription>  $subscriptions  every production row, any status
     * @return array<string, array{converted: int, lapsed_trial: int, still_in_trial: int, rate: ?int}>
     */
    private static function conversion(Collection $subscriptions): array
    {
        return collect(SubscriptionPlan::cases())->mapWithKeys(function (SubscriptionPlan $plan) use ($subscriptions) {
            [$trials, $converted] = self::onPlan($subscriptions, $plan)->partition(fn (Subscription $s) => $s->period_type === SubscriptionPeriodType::Trial);
            [$stillInTrial, $lapsed] = $trials->partition(fn (Subscription $s) => $s->isActive());
            $finished = $converted->count() + $lapsed->count();

            return [$plan->value => [
                'converted' => $converted->count(),
                'lapsed_trial' => $lapsed->count(),
                'still_in_trial' => $stillInTrial->count(),
                'rate' => $finished > 0 ? (int) round($converted->count() / $finished * 100) : null,
            ]];
        })->all();
    }

    /**
     * @param  Collection<int, Subscription>  $subscriptions
     * @return Collection<int, Subscription>
     */
    private static function onPlan(Collection $subscriptions, SubscriptionPlan $plan): Collection
    {
        return $subscriptions->filter(fn (Subscription $s) => SubscriptionPlan::fromProductId($s->product_id) === $plan);
    }

    /**
     * @param  Collection<int, Subscription>  $subscriptions
     */
    private static function monthlyUsd(Collection $subscriptions): float
    {
        return round($subscriptions->sum(fn (Subscription $s) => match (SubscriptionPlan::fromProductId($s->product_id)) {
            SubscriptionPlan::Monthly => (float) $s->price,
            SubscriptionPlan::Yearly => (float) $s->price / 12,
            null => 0.0,
        }), 2);
    }
}
