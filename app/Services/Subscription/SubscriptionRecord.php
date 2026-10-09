<?php

namespace App\Services\Subscription;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The one place a user's subscription row is written. A RevenueCat webhook
 * and a REST snapshot of the subscriber both go through here, so the same
 * {@see SubscriptionState} always leaves the same row.
 *
 * It owns the rules every writer would otherwise have to remember:
 *  - the stale-event high-water mark: RevenueCat does not guarantee ordering
 *    or exactly-once delivery, so a state whose event time is at or before the
 *    last one applied is ignored (a late EXPIRATION would otherwise revoke a
 *    paying user);
 *  - only a purchase creates a row; a status change with no row is a no-op;
 *  - the acquisition partner is frozen when the row is created;
 *  - a renewal keeps the original purchase time and any price it does not
 *    report.
 *
 * Event times are epoch milliseconds, as RevenueCat sends them. A state with
 * no event time is applied unconditionally and moves no mark.
 */
final class SubscriptionRecord
{
    /**
     * Write $state onto $user's subscription.
     *
     * @return bool false when the event is stale and nothing was written
     */
    public static function apply(User $user, SubscriptionState $state, ?int $eventAtMs = null): bool
    {
        return DB::transaction(function () use ($user, $state, $eventAtMs) {
            $subscription = Subscription::where('user_id', $user->id)->first();

            if (self::isStale($subscription, $eventAtMs)) {
                return false;
            }

            if (! $subscription && ! $state->isPurchase()) {
                return true;
            }

            $subscription ??= new Subscription([
                'user_id' => $user->id,
                'partner_id' => $user->partner_id,
            ]);

            if ($state->isPurchase()) {
                self::fillPurchase($subscription, $user, $state);
            }

            $subscription->fill(['status' => $state->status, ...$state->dates()]);

            if ($eventAtMs !== null) {
                $subscription->last_event_at_ms = $eventAtMs;
            }

            $subscription->save();

            return true;
        });
    }

    /**
     * An event that changes nothing about the subscription (a price-change
     * notice, an event type we do not handle) still moves the high-water mark,
     * so an older event arriving after it is ignored.
     *
     * @return bool false when the event is stale
     */
    public static function noteEvent(User $user, ?int $eventAtMs): bool
    {
        if ($eventAtMs === null) {
            return true;
        }

        return DB::transaction(function () use ($user, $eventAtMs) {
            $subscription = Subscription::where('user_id', $user->id)->first();

            if (self::isStale($subscription, $eventAtMs)) {
                return false;
            }

            $subscription?->update(['last_event_at_ms' => $eventAtMs]);

            return true;
        });
    }

    /**
     * Move a subscription from one of $fromUserIds to $to (RevenueCat TRANSFER:
     * restore purchases, reinstall, family sharing). A user holds one row, so
     * any row $to already had is superseded and deleted.
     *
     * @param  array<int, int>  $fromUserIds
     * @return Subscription|null the moved subscription, or null when none of
     *                           $fromUserIds had one
     */
    public static function transfer(array $fromUserIds, User $to): ?Subscription
    {
        return DB::transaction(function () use ($fromUserIds, $to) {
            $subscription = Subscription::whereIn('user_id', $fromUserIds)->first();

            if (! $subscription) {
                return null;
            }

            Subscription::where('user_id', $to->id)
                ->whereKeyNot($subscription->id)
                ->delete();

            $subscription->user_id = $to->id;
            $subscription->save();

            return $subscription;
        });
    }

    private static function isStale(?Subscription $subscription, ?int $eventAtMs): bool
    {
        // <= so exact-duplicate deliveries (same timestamp) are also ignored.
        return $eventAtMs !== null
            && $subscription?->last_event_at_ms !== null
            && $eventAtMs <= $subscription->last_event_at_ms;
    }

    private static function fillPurchase(Subscription $subscription, User $user, SubscriptionState $state): void
    {
        $purchase = $state->purchase();

        if ($state->isNewPurchase()) {
            // A new purchase is taken whole. It currently also re-stamps the
            // acquisition partner on an existing row; 026/03 freezes it.
            $subscription->partner_id = $user->partner_id;
            $subscription->fill([...$purchase, 'purchased_at' => $purchase['purchased_at'] ?? now()]);

            return;
        }

        $subscription->fill([
            ...$purchase,
            'price' => $purchase['price'] ?? $subscription->price,
            'currency' => $purchase['currency'] ?? $subscription->currency,
            'purchased_at' => $subscription->purchased_at ?? $purchase['purchased_at'] ?? now(),
        ]);
    }
}
