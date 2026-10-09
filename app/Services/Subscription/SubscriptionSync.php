<?php

namespace App\Services\Subscription;

use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Repairs a user's subscription row from RevenueCat on demand: reads the
 * subscriber from the RevenueCat REST API (v1), takes the subscription behind
 * the `app_access` entitlement, and writes it through
 * {@see SubscriptionRecord::apply()} — the same path a webhook takes, so a
 * sync and a webhook describing the same state leave the same row.
 *
 * A snapshot is current by definition: it is applied with now() as its event
 * time, so an older webhook processed afterwards cannot undo it.
 *
 * It never guesses. Without the secret API key, or when RevenueCat cannot be
 * read, it throws {@see SubscriptionSyncFailed} and writes nothing. When the
 * subscriber has nothing we can apply (no `app_access` entitlement, a store
 * we do not sell through, a sandbox purchase in production) the row is left
 * as it is.
 */
final class SubscriptionSync
{
    private const ENTITLEMENT = 'app_access';

    /**
     * @throws SubscriptionSyncFailed
     */
    public static function run(User $user): void
    {
        $subscription = self::fetchSubscription($user);

        if ($subscription === null) {
            return;
        }

        if (($subscription['is_sandbox'] ?? false) && app()->isProduction()) {
            Log::info('Subscription sync: skipping sandbox subscription in production', ['user_id' => $user->id]);

            return;
        }

        if (SubscriptionState::store($subscription['store'] ?? null) === null) {
            Log::info('Subscription sync: store is not one we sell through', [
                'user_id' => $user->id,
                'store' => $subscription['store'] ?? null,
            ]);

            return;
        }

        SubscriptionRecord::apply($user, self::stateOf($subscription), now()->getTimestampMs());
    }

    /**
     * The store subscription behind the user's app_access entitlement, with
     * its product id added, or null when they have none.
     *
     * @return array<string, mixed>|null
     */
    private static function fetchSubscription(User $user): ?array
    {
        $key = config('services.revenuecat.secret_api_key');

        if (! is_string($key) || $key === '') {
            Log::error('Subscription sync: REVENUECAT_SECRET_API_KEY is not configured', ['user_id' => $user->id]);

            throw SubscriptionSyncFailed::notConfigured();
        }

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->timeout(10)
                ->get(config('services.revenuecat.base_url').'/subscribers/'.$user->id);
        } catch (ConnectionException $e) {
            Log::error('Subscription sync: RevenueCat unreachable', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            throw SubscriptionSyncFailed::upstream();
        }

        if (! $response->successful()) {
            Log::error('Subscription sync: RevenueCat answered an error', [
                'user_id' => $user->id,
                'status' => $response->status(),
            ]);

            throw SubscriptionSyncFailed::upstream();
        }

        $subscriber = $response->json('subscriber');
        $productId = $subscriber['entitlements'][self::ENTITLEMENT]['product_identifier'] ?? null;
        $subscription = is_string($productId) ? ($subscriber['subscriptions'][$productId] ?? null) : null;

        if (! is_array($subscription)) {
            Log::info('Subscription sync: no app_access subscription at RevenueCat', ['user_id' => $user->id]);

            return null;
        }

        return [...$subscription, 'product_id' => $productId];
    }

    /** @param array<string, mixed> $subscription */
    private static function stateOf(array $subscription): SubscriptionState
    {
        $refundedAt = self::date($subscription['refunded_at'] ?? null);
        $cancelledAt = self::date($subscription['unsubscribe_detected_at'] ?? null);
        $expiresAt = self::date($subscription['expires_date'] ?? null);
        $billingIssue = ($subscription['billing_issues_detected_at'] ?? null) !== null;

        // A billing issue keeps access through the store's grace period.
        if ($billingIssue) {
            $expiresAt = self::date($subscription['grace_period_expires_date'] ?? null) ?? $expiresAt;
        }

        [$status, $expiresAt, $cancelledAt] = match (true) {
            // The money went back: access ended when it did.
            $refundedAt !== null => [SubscriptionStatus::Expired, $refundedAt, $refundedAt],
            $expiresAt !== null && $expiresAt->isPast() => [SubscriptionStatus::Expired, $expiresAt, $cancelledAt],
            $billingIssue => [SubscriptionStatus::BillingIssue, $expiresAt, $cancelledAt],
            $cancelledAt !== null => [SubscriptionStatus::Cancelled, $expiresAt, $cancelledAt],
            default => [SubscriptionStatus::Active, $expiresAt, null],
        };

        return SubscriptionState::snapshot(
            status: $status,
            productId: $subscription['product_id'],
            store: $subscription['store'],
            periodType: $subscription['period_type'] ?? null,
            purchasedAt: self::date($subscription['original_purchase_date'] ?? $subscription['purchase_date'] ?? null),
            expiresAt: $expiresAt,
            cancelledAt: $cancelledAt,
            environment: ($subscription['is_sandbox'] ?? false) ? 'sandbox' : 'production',
        );
    }

    private static function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        // Stored in the app's zone, as the webhook does (see its fromMs()).
        return Carbon::parse($value)->setTimezone(config('app.timezone'));
    }
}
