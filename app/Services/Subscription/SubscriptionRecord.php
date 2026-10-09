<?php

namespace App\Services\Subscription;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
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
 *  - an extension only ever pushes the expiry later, reopening an expired
 *    subscription and keeping any other status;
 *  - a stale event still fills in a price the row does not know: a REST
 *    snapshot reports no USD price, so a sync that beat the purchase
 *    webhook leaves it for that webhook, which is stale by then.
 *
 * Event times are epoch milliseconds, as RevenueCat sends them. A state with
 * no event time (a webhook missing event_timestamp_ms) is applied
 * unconditionally and moves no mark. A REST snapshot has no event time of its
 * own but is current by definition: pass now() in milliseconds, so an older
 * webhook arriving later cannot undo it.
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
                self::fillUnknownPrice($subscription, $state);

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
                self::fillPurchase($subscription, $state);
            }

            if ($state->extendsTo() !== null) {
                self::extend($subscription, $state->extendsTo());
            } else {
                $subscription->fill($state->dates());
                $subscription->status = $state->status ?? $subscription->status ?? SubscriptionStatus::Active;
            }

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
     * Move the subscriptions of $fromUserIds to $to (RevenueCat TRANSFER:
     * restore purchases, reinstall, family sharing). Ownership moves, so none
     * of the source users keeps a row. A user holds one row, so $to gets the
     * best of them (one that grants access, then the latest expiry); the rest,
     * and any row $to already had, are superseded and deleted — unless $to's
     * own row grants access and none of the transferred ones does (restoring
     * an old, expired purchase onto an account that pays): then $to keeps its
     * own and nothing moves.
     *
     * The moved row carries the highest stale-event mark of every row it
     * replaces and the transfer's own time, so an event that happened before
     * the transfer, for either side, cannot undo it.
     *
     * @param  array<int, int>  $fromUserIds
     * @return Subscription|null the moved subscription, or null when nothing
     *                           moved (none of $fromUserIds had one, or $to
     *                           kept its own)
     */
    public static function transfer(array $fromUserIds, User $to, ?int $eventAtMs = null): ?Subscription
    {
        return DB::transaction(function () use ($fromUserIds, $to, $eventAtMs) {
            $transferred = Subscription::whereIn('user_id', $fromUserIds)
                ->where('user_id', '!=', $to->id)
                ->get()
                ->sortByDesc(fn (Subscription $s) => [$s->isActive(), $s->expires_at?->getTimestamp() ?? 0])
                ->values();
            $subscription = $transferred->first();

            if (! $subscription) {
                return null;
            }

            $receiversOwn = Subscription::where('user_id', $to->id)->first();

            if ($receiversOwn?->isActive() && ! $subscription->isActive()) {
                return null;
            }

            $superseded = $transferred->slice(1)->push($receiversOwn)->filter();
            $marks = $superseded->pluck('last_event_at_ms')
                ->push($subscription->last_event_at_ms, $eventAtMs)
                ->filter(fn ($mark) => $mark !== null);

            $superseded->each->delete();

            $subscription->user_id = $to->id;
            $subscription->last_event_at_ms = $marks->max();
            $subscription->save();

            return $subscription;
        });
    }

    private static function extend(Subscription $subscription, Carbon $until): void
    {
        if ($subscription->expires_at !== null && $subscription->expires_at->gte($until)) {
            return;
        }

        $subscription->expires_at = $until;

        if ($subscription->status === SubscriptionStatus::Expired) {
            $subscription->status = SubscriptionStatus::Active;
        }
    }

    private static function isStale(?Subscription $subscription, ?int $eventAtMs): bool
    {
        // <= so exact-duplicate deliveries (same timestamp) are also ignored.
        return $eventAtMs !== null
            && $subscription?->last_event_at_ms !== null
            && $eventAtMs <= $subscription->last_event_at_ms;
    }

    private static function fillUnknownPrice(Subscription $subscription, SubscriptionState $state): void
    {
        $price = $state->purchase()['price'] ?? null;

        if ($subscription->price !== null || $price === null) {
            return;
        }

        $subscription->update(['price' => $price, 'currency' => $state->purchase()['currency'] ?? $subscription->currency]);
    }

    private static function fillPurchase(Subscription $subscription, SubscriptionState $state): void
    {
        $purchase = $state->purchase();

        if ($state->isNewPurchase()) {
            // A new purchase is taken whole, except the acquisition partner:
            // it was frozen when the row was created and a later purchase
            // under another partner does not re-attribute it.
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
