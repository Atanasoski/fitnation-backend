<?php

namespace App\Services\Admin;

use App\Enums\AccessSource;
use App\Enums\AdminChangeKind;
use App\Enums\SubscriptionPeriod;
use App\Enums\SubscriptionPeriodType;
use App\Enums\SubscriptionStatus;
use App\Models\AdminChange;
use App\Models\Partner;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The Access Source rule (CONTEXT.md), with two faces that must agree: the
 * source (and its facts) of given users, and a query constraint "users whose
 * source is X" that runs in SQL so the Users list can filter and the Overview
 * can count. tests/Feature/Admin/AccessSourceTest.php holds them to each other.
 *
 * In order, the first that holds:
 * - a subscription that grants access (Subscription::isActive(): an
 *   access-granting status and expires_at in the future) — Active reads
 *   Trial on a trial period and Subscribed otherwise; Cancelled reads
 *   "Cancelled, paid until"; Billing issue; Paused;
 * - Sponsored — the user's partner is an active Sponsoring Partner whose
 *   sponsorship has not run out (Partner::isSponsoringMembers());
 * - Signup Trial — User::isOnSignupTrial();
 * - Complimentary — User::hasComplimentaryAccess() (the two are exclusive:
 *   one date, one recorded kind);
 * - None.
 *
 * These are the facts User::entitlements() reads, through the same
 * predicates, but read **ignoring** `subscriptions.enforced`: with
 * enforcement off a user with None still gets in, and None is exactly who the
 * paywall would stop.
 *
 * Loads what it needs itself; callers never eager-load for it.
 */
final class AccessSources
{
    public static function for(User $user): Access
    {
        return self::forUsers(collect([$user]))[$user->id];
    }

    /**
     * Just the source, read through the user's own subscription and partner
     * relations — free when they are loaded (as GET /user loads them), and
     * no lookup of who granted Complimentary Access. For the API payload.
     */
    public static function sourceOf(User $user): AccessSource
    {
        return self::resolve($user, $user->subscription, $user->partner)->source;
    }

    /**
     * Two queries for any number of users: their subscriptions and partners,
     * plus one for who granted any Complimentary Access.
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, Access> keyed by user id
     */
    public static function forUsers(Collection $users): Collection
    {
        if ($users->isEmpty()) {
            return collect();
        }

        $subscriptions = Subscription::query()
            ->whereIn('user_id', $users->map(fn (User $user) => $user->getKey())->all())
            ->get()
            ->keyBy('user_id');

        $partnerIds = $users->pluck('partner_id')->filter()->unique()->values()->all();
        $partners = $partnerIds === [] ? collect() : Partner::query()->whereKey($partnerIds)->get()->keyBy('id');

        $access = $users->mapWithKeys(fn (User $user) => [
            $user->id => self::resolve($user, $subscriptions[$user->id] ?? null, $partners[$user->partner_id] ?? null),
        ]);

        return self::withGrantors($access);
    }

    /**
     * Name the admin behind each Complimentary Access: whoever made the latest
     * grant in the admin change record. One more query, only when some user
     * reads Complimentary; a grant made outside the panel names nobody.
     *
     * @param  Collection<int, Access>  $access  keyed by user id
     * @return Collection<int, Access>
     */
    private static function withGrantors(Collection $access): Collection
    {
        $complimentary = $access->filter(fn (Access $a) => $a->source === AccessSource::Complimentary);
        if ($complimentary->isEmpty()) {
            return $access;
        }

        $grants = AdminChange::query()
            ->whereIn('user_id', $complimentary->keys()->all())
            ->where('kind', AdminChangeKind::ComplimentaryAccess)
            ->whereNotNull('until')
            ->with('admin:id,name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->keyBy('user_id');

        return $access->map(fn (Access $a, int $userId) => isset($complimentary[$userId], $grants[$userId])
            ? new Access(AccessSource::Complimentary, until: $a->until, grantedBy: $grants[$userId]->admin?->name)
            : $a);
    }

    /**
     * Narrow a users query to those whose source is $source. Leaves the
     * soft-delete scope as the caller set it.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public static function constrain(Builder $query, AccessSource $source): Builder
    {
        $subscription = fn (SubscriptionStatus $status, ?callable $period = null) => $query->whereHas(
            'subscription',
            function (Builder $subscriptions) use ($status, $period) {
                $subscriptions->active()->where('status', $status);
                if ($period !== null) {
                    $period($subscriptions);
                }
            },
        );

        $isTrial = fn (Builder $q) => $q->where('period_type', SubscriptionPeriodType::Trial);

        return match ($source) {
            AccessSource::Subscribed => $subscription(SubscriptionStatus::Active, fn (Builder $q) => $q->where('period_type', '!=', SubscriptionPeriodType::Trial)),
            AccessSource::Trial => $subscription(SubscriptionStatus::Active, $isTrial),
            AccessSource::Cancelled => $subscription(SubscriptionStatus::Cancelled),
            AccessSource::BillingIssue => $subscription(SubscriptionStatus::BillingIssue),
            AccessSource::Paused => $subscription(SubscriptionStatus::Paused),
            AccessSource::Sponsored => self::withoutSubscriptionAccess($query)
                ->whereHas('partner', fn (Builder $partners) => $partners->sponsoringMembers()),
            AccessSource::SignupTrial => self::withoutPaidAccess($query)->onSignupTrial(),
            AccessSource::Complimentary => self::withoutPaidAccess($query)->withComplimentaryAccess(),
            // NOT (ends > now) is NULL for a NULL date in SQL, so the negated
            // group also requires the date to be set: no date at all reads None.
            AccessSource::None => self::withoutPaidAccess($query)
                ->whereNot(fn (Builder $q) => $q->withFreeAccess()->whereNotNull('users.grace_period_ends_at')),
        };
    }

    /**
     * Neither a subscription nor a Sponsoring Partner grants access: who the
     * free-access sources (and None) are read from.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private static function withoutPaidAccess(Builder $query): Builder
    {
        return self::withoutSponsorship(self::withoutSubscriptionAccess($query));
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private static function withoutSubscriptionAccess(Builder $query): Builder
    {
        return $query->whereDoesntHave('subscription', fn (Builder $subscriptions) => $subscriptions->active());
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private static function withoutSponsorship(Builder $query): Builder
    {
        return $query->whereDoesntHave('partner', fn (Builder $partners) => $partners->sponsoringMembers());
    }

    private static function resolve(User $user, ?Subscription $subscription, ?Partner $partner): Access
    {
        if ($subscription?->isActive()) {
            return new Access(
                source: match ($subscription->status) {
                    SubscriptionStatus::Active => $subscription->isInTrial() ? AccessSource::Trial : AccessSource::Subscribed,
                    SubscriptionStatus::Cancelled => AccessSource::Cancelled,
                    SubscriptionStatus::BillingIssue => AccessSource::BillingIssue,
                    SubscriptionStatus::Paused => AccessSource::Paused,
                },
                until: $subscription->expires_at,
                productId: $subscription->product_id,
                period: SubscriptionPeriod::fromProductId($subscription->product_id)?->label(),
                store: $subscription->store,
                cancelledAt: $subscription->cancelled_at,
            );
        }

        if ($partner?->isSponsoringMembers()) {
            return new Access(AccessSource::Sponsored, until: $partner->plan_expires_at, sponsor: $partner);
        }

        if ($user->isOnSignupTrial()) {
            return new Access(AccessSource::SignupTrial, until: $user->grace_period_ends_at);
        }

        if ($user->hasComplimentaryAccess()) {
            return new Access(AccessSource::Complimentary, until: $user->grace_period_ends_at);
        }

        return new Access(AccessSource::None);
    }
}
