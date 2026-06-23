<?php

namespace App\Webhooks\RevenueCat;

use App\Enums\SubscriptionPeriodType;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionStore;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;
use Throwable;

class ProcessRevenueCatWebhook extends ProcessWebhookJob
{
    // Cap retries so a permanently unmatchable user ID doesn't clog the queue.
    public int $tries = 5;

    public function handle(): void
    {
        $event = $this->webhookCall->payload['event'] ?? null;

        if (! is_array($event) || empty($event['type'])) {
            Log::warning('RevenueCat webhook missing event payload', [
                'webhook_call_id' => $this->webhookCall->id,
            ]);

            return;
        }

        // Skip sandbox events in production to avoid polluting real subscription data.
        $environment = strtolower($event['environment'] ?? 'production');
        if ($environment === 'sandbox' && app()->isProduction()) {
            Log::info('Skipping RevenueCat sandbox event in production', [
                'type' => $event['type'],
                'webhook_call_id' => $this->webhookCall->id,
            ]);

            return;
        }

        $type = $event['type'];

        if ($type === 'TEST') {
            Log::info('RevenueCat TEST event received — skipping', ['webhook_call_id' => $this->webhookCall->id]);

            return;
        }

        // TRANSFER moves a subscription between app_user_ids (restore-purchases,
        // reinstall, family sharing). It has its own from/to identity fields, so
        // handle it before the single-user resolution below.
        if ($type === 'TRANSFER') {
            DB::transaction(fn () => $this->handleTransfer($event));

            return;
        }

        $user = $this->resolveUser($event);

        if (! $user) {
            // Throw so the queue retries — the webhook payload is safely stored
            // in webhook_calls and can be replayed via `revenuecat:replay-failed`.
            throw new RuntimeException(sprintf(
                'RevenueCat webhook for unresolvable user (app_user_id: %s, event: %s, webhook_call: %d)',
                $event['app_user_id'] ?? 'null',
                $type,
                $this->webhookCall->id,
            ));
        }

        $eventTs = isset($event['event_timestamp_ms']) ? (int) $event['event_timestamp_ms'] : null;

        DB::transaction(function () use ($user, $event, $type, $eventTs) {
            // RevenueCat does not guarantee ordering or exactly-once delivery.
            // Ignore any event that is older than or identical to the last one
            // already applied to this subscription (e.g. a late EXPIRATION that
            // arrives after a RENEWAL would otherwise revoke a paying user).
            if ($this->isStale($user, $eventTs)) {
                Log::info('Skipping out-of-order/duplicate RevenueCat event', [
                    'type' => $type,
                    'user_id' => $user->id,
                    'event_timestamp_ms' => $eventTs,
                    'webhook_call_id' => $this->webhookCall->id,
                ]);

                return;
            }

            match ($type) {
                'INITIAL_PURCHASE' => $this->handleInitialPurchase($user, $event),
                'RENEWAL', 'PRODUCT_CHANGE' => $this->handleRenewal($user, $event),
                // PRICE_CHANGE only announces a future price; it carries no new
                // expiration, so running it through renewal would wipe expires_at.
                'PRICE_CHANGE' => Log::info('RevenueCat PRICE_CHANGE noted — no subscription change applied', [
                    'user_id' => $user->id,
                    'webhook_call_id' => $this->webhookCall->id,
                ]),
                'CANCELLATION' => $this->handleCancellation($user, $event),
                'UNCANCELLATION', 'SUBSCRIPTION_RESUMED' => $this->handleUncancellation($user),
                'EXPIRATION' => $this->handleExpiration($user),
                'BILLING_ISSUE' => $this->handleBillingIssue($user),
                'SUBSCRIPTION_PAUSED' => $this->handlePaused($user),
                default => Log::info('Unhandled RevenueCat event type', [
                    'type' => $type,
                    'webhook_call_id' => $this->webhookCall->id,
                    'event' => $event,
                ]),
            };

            // Advance the high-water mark so later-arriving older events are skipped.
            if ($eventTs !== null) {
                Subscription::where('user_id', $user->id)->update(['last_event_at_ms' => $eventTs]);
            }
        });
    }

    /**
     * Persist the failure onto the webhook_calls row so the call can be replayed
     * later with `php artisan revenuecat:replay-failed`.
     */
    public function failed(Throwable $exception): void
    {
        $this->webhookCall?->saveException($exception);
    }

    /**
     * Resolve the local user from any of the identifiers RevenueCat may send.
     * The mobile app MUST set RevenueCat's appUserID to the numeric users.id;
     * we also check original_app_user_id and aliases to cover anonymous-then-
     * identified purchase flows.
     */
    private function resolveUser(array $event): ?User
    {
        $candidates = array_merge(
            [$event['app_user_id'] ?? null, $event['original_app_user_id'] ?? null],
            (array) ($event['aliases'] ?? []),
        );

        $ids = $this->numericIds($candidates);

        if (empty($ids)) {
            return null;
        }

        return User::whereIn('id', $ids)->first();
    }

    /**
     * Keep only identifiers that can match a users.id. RevenueCat app_user_ids
     * are either our numeric id or an opaque anonymous id ($RCAnonymousID:...);
     * filtering to digits also avoids MySQL coercing a non-numeric string into
     * an unintended row match.
     *
     * @param  array<int, mixed>  $values
     * @return array<int, int>
     */
    private function numericIds(array $values): array
    {
        return collect($values)
            ->filter(fn ($v) => is_string($v) || is_int($v))
            ->filter(fn ($v) => ctype_digit((string) $v))
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
    }

    private function isStale(User $user, ?int $eventTs): bool
    {
        if ($eventTs === null) {
            return false;
        }

        $last = Subscription::where('user_id', $user->id)->value('last_event_at_ms');

        // <= so exact-duplicate deliveries (same timestamp) are also ignored.
        return $last !== null && $eventTs <= $last;
    }

    private function handleInitialPurchase(User $user, array $event): void
    {
        Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'partner_id' => $user->partner_id,
                'product_id' => $event['product_id'],
                'store' => $this->mapStore($event['store'] ?? null),
                'status' => SubscriptionStatus::Active,
                'period_type' => $this->mapPeriodType($event['period_type'] ?? null),
                'price' => $event['price'] ?? null,
                'currency' => $event['currency'] ?? null,
                'purchased_at' => $this->fromMs($event['purchased_at_ms'] ?? null) ?? now(),
                'expires_at' => $this->fromMs($event['expiration_at_ms'] ?? null),
                'cancelled_at' => null,
                'environment' => strtolower($event['environment'] ?? 'production'),
            ]
        );
    }

    private function handleRenewal(User $user, array $event): void
    {
        $subscription = Subscription::firstOrNew(['user_id' => $user->id]);

        // Preserve partner_id (acquisition gym) — never overwritten after initial purchase.
        if (! $subscription->exists) {
            $subscription->partner_id = $user->partner_id;
        }

        $subscription->fill([
            'product_id' => $event['product_id'],
            'store' => $this->mapStore($event['store'] ?? null),
            'status' => SubscriptionStatus::Active,
            'period_type' => SubscriptionPeriodType::Normal,
            'price' => $event['price'] ?? $subscription->price,
            'currency' => $event['currency'] ?? $subscription->currency,
            'expires_at' => $this->fromMs($event['expiration_at_ms'] ?? null),
            'cancelled_at' => null,
            'environment' => strtolower($event['environment'] ?? 'production'),
        ]);

        if (! $subscription->purchased_at) {
            $subscription->purchased_at = $this->fromMs($event['purchased_at_ms'] ?? null) ?? now();
        }

        $subscription->save();
    }

    private function handleCancellation(User $user, array $event): void
    {
        $reason = strtoupper((string) ($event['cancel_reason'] ?? $event['cancellation_reason'] ?? ''));

        // A refund (CUSTOMER_SUPPORT) returns the customer's money — revoke
        // access immediately rather than honouring the remaining paid period.
        if ($reason === 'CUSTOMER_SUPPORT') {
            Subscription::where('user_id', $user->id)->update([
                'status' => SubscriptionStatus::Expired,
                'cancelled_at' => now(),
                'expires_at' => now(),
            ]);

            Log::info('RevenueCat refund — access revoked immediately', [
                'user_id' => $user->id,
                'webhook_call_id' => $this->webhookCall->id,
            ]);

            return;
        }

        // Auto-renew turned off — access continues until expires_at (grace period).
        Subscription::where('user_id', $user->id)->update([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    private function handleUncancellation(User $user): void
    {
        Subscription::where('user_id', $user->id)->update([
            'status' => SubscriptionStatus::Active,
            'cancelled_at' => null,
        ]);
    }

    private function handleExpiration(User $user): void
    {
        Subscription::where('user_id', $user->id)->update([
            'status' => SubscriptionStatus::Expired,
        ]);
    }

    private function handleBillingIssue(User $user): void
    {
        Subscription::where('user_id', $user->id)->update([
            'status' => SubscriptionStatus::BillingIssue,
        ]);
    }

    // Android-only: user paused — access ends at expires_at, resumes automatically.
    private function handlePaused(User $user): void
    {
        Subscription::where('user_id', $user->id)->update([
            'status' => SubscriptionStatus::Paused,
        ]);
    }

    /**
     * RevenueCat TRANSFER moves a subscription between app_user_ids. Re-point the
     * existing subscription to the new (target) user so they keep access. The
     * source user is left without a subscription, which is correct — ownership moved.
     */
    private function handleTransfer(array $event): void
    {
        $targetIds = $this->numericIds((array) ($event['transferred_to'] ?? []));
        $sourceIds = $this->numericIds((array) ($event['transferred_from'] ?? []));

        $target = User::whereIn('id', $targetIds)->first();

        if (! $target) {
            // Retry — the target user may not be registered yet.
            throw new RuntimeException(sprintf(
                'RevenueCat TRANSFER target unresolved (to: %s, webhook_call: %d)',
                implode(',', array_map('strval', $targetIds)) ?: 'null',
                $this->webhookCall->id,
            ));
        }

        $subscription = Subscription::whereIn('user_id', $sourceIds)->first();

        if (! $subscription) {
            Log::info('RevenueCat TRANSFER: no source subscription to move', [
                'to' => $target->id,
                'from' => $sourceIds,
                'webhook_call_id' => $this->webhookCall->id,
            ]);

            return;
        }

        // The unique(user_id) constraint allows the target only one subscription —
        // drop any superseded one before re-pointing the transferred subscription.
        Subscription::where('user_id', $target->id)
            ->whereKeyNot($subscription->id)
            ->delete();

        $subscription->user_id = $target->id;
        $subscription->save();

        Log::info('RevenueCat TRANSFER applied', [
            'from' => $sourceIds,
            'to' => $target->id,
            'subscription_id' => $subscription->id,
            'webhook_call_id' => $this->webhookCall->id,
        ]);
    }

    private function mapStore(?string $store): SubscriptionStore
    {
        return match (strtoupper((string) $store)) {
            'APP_STORE' => SubscriptionStore::AppStore,
            'PLAY_STORE' => SubscriptionStore::PlayStore,
            default => SubscriptionStore::AppStore,
        };
    }

    private function mapPeriodType(?string $type): SubscriptionPeriodType
    {
        return match (strtoupper((string) $type)) {
            'TRIAL' => SubscriptionPeriodType::Trial,
            'INTRO' => SubscriptionPeriodType::Intro,
            'PROMOTIONAL' => SubscriptionPeriodType::Promotional,
            default => SubscriptionPeriodType::Normal,
        };
    }

    private function fromMs(?int $ms): ?Carbon
    {
        if (! $ms) {
            return null;
        }

        return Carbon::createFromTimestampMs($ms);
    }
}
