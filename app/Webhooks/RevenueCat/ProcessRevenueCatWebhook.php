<?php

namespace App\Webhooks\RevenueCat;

use App\Models\User;
use App\Services\Subscription\SubscriptionRecord;
use App\Services\Subscription\SubscriptionState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;
use Throwable;

class ProcessRevenueCatWebhook extends ProcessWebhookJob
{
    // Cap retries so a permanently unmatchable user ID doesn't clog the queue.
    public int $tries = 5;

    // Events that write a subscription row and so need product_id and store.
    private const PURCHASE_EVENTS = ['INITIAL_PURCHASE', 'RENEWAL', 'PRODUCT_CHANGE'];

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
            $this->handleTransfer($event);

            return;
        }

        // A purchase event writes a subscription row, so a payload missing what
        // that row needs can never be applied: record the failure on the call
        // (visible to ops, replayable after a fix) without burning the retries.
        if (in_array($type, self::PURCHASE_EVENTS, true)
            && ($problem = $this->purchasePayloadProblem($event)) !== null) {
            $this->reject($type, $problem);

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
        $state = $this->stateFor($type, $event);

        $applied = $state
            ? SubscriptionRecord::apply($user, $state, $eventTs)
            : SubscriptionRecord::noteEvent($user, $eventTs);

        if (! $applied) {
            Log::info('Skipping out-of-order/duplicate RevenueCat event', [
                'type' => $type,
                'user_id' => $user->id,
                'event_timestamp_ms' => $eventTs,
                'webhook_call_id' => $this->webhookCall->id,
            ]);

            return;
        }

        $this->logApplied($type, $event, $user, $state);
    }

    /**
     * The subscription state an event describes, or null when it changes
     * nothing about the subscription.
     */
    private function stateFor(string $type, array $event): ?SubscriptionState
    {
        $environment = $event['environment'] ?? 'production';

        return match ($type) {
            'INITIAL_PURCHASE' => SubscriptionState::purchased(
                productId: $event['product_id'],
                store: $event['store'] ?? null,
                periodType: $event['period_type'] ?? null,
                price: $event['price'] ?? null,
                currency: $event['currency'] ?? null,
                purchasedAt: $this->fromMs($event['purchased_at_ms'] ?? null),
                expiresAt: $this->fromMs($event['expiration_at_ms'] ?? null),
                environment: $environment,
            ),
            'RENEWAL', 'PRODUCT_CHANGE' => SubscriptionState::renewed(
                productId: $event['product_id'],
                store: $event['store'] ?? null,
                price: $event['price'] ?? null,
                currency: $event['currency'] ?? null,
                purchasedAt: $this->fromMs($event['purchased_at_ms'] ?? null),
                expiresAt: $this->fromMs($event['expiration_at_ms'] ?? null),
                environment: $environment,
            ),
            // A refund (CUSTOMER_SUPPORT) returns the customer's money — revoke
            // access immediately rather than honouring the remaining paid period.
            // Otherwise auto-renew was turned off and access continues until expiry.
            'CANCELLATION' => $this->isRefund($event)
                ? SubscriptionState::refunded(now())
                : SubscriptionState::cancelled(now()),
            'UNCANCELLATION', 'SUBSCRIPTION_RESUMED' => SubscriptionState::uncancelled(),
            'EXPIRATION' => SubscriptionState::expired(),
            'BILLING_ISSUE' => SubscriptionState::billingIssue(),
            'SUBSCRIPTION_PAUSED' => SubscriptionState::paused(),
            // PRICE_CHANGE only announces a future price; it carries no new
            // expiration, so running it through renewal would wipe expires_at.
            // It and unhandled types change nothing but the high-water mark.
            default => null,
        };
    }

    private function isRefund(array $event): bool
    {
        $reason = strtoupper((string) ($event['cancel_reason'] ?? $event['cancellation_reason'] ?? ''));

        return $reason === 'CUSTOMER_SUPPORT';
    }

    private function logApplied(string $type, array $event, User $user, ?SubscriptionState $state): void
    {
        $context = ['user_id' => $user->id, 'webhook_call_id' => $this->webhookCall->id];

        if ($type === 'PRICE_CHANGE') {
            Log::info('RevenueCat PRICE_CHANGE noted — no subscription change applied', $context);
        } elseif ($state === null) {
            Log::info('Unhandled RevenueCat event type', [
                'type' => $type,
                'webhook_call_id' => $context['webhook_call_id'],
                'event' => $event,
            ]);
        } elseif ($type === 'CANCELLATION' && $this->isRefund($event)) {
            Log::info('RevenueCat refund — access revoked immediately', $context);
        }
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

        $subscription = SubscriptionRecord::transfer($sourceIds, $target);

        if (! $subscription) {
            Log::info('RevenueCat TRANSFER: no source subscription to move', [
                'to' => $target->id,
                'from' => $sourceIds,
                'webhook_call_id' => $this->webhookCall->id,
            ]);

            return;
        }

        Log::info('RevenueCat TRANSFER applied', [
            'from' => $sourceIds,
            'to' => $target->id,
            'subscription_id' => $subscription->id,
            'webhook_call_id' => $this->webhookCall->id,
        ]);
    }

    private function purchasePayloadProblem(array $event): ?string
    {
        $productId = $event['product_id'] ?? null;

        if (! is_string($productId) || $productId === '') {
            return 'product_id is missing';
        }

        if (SubscriptionState::store($event['store'] ?? null) === null) {
            return sprintf('store %s is not one we sell through', json_encode($event['store'] ?? null));
        }

        return null;
    }

    private function reject(string $type, string $problem): void
    {
        $this->webhookCall->saveException(new RuntimeException(sprintf(
            'RevenueCat %s rejected: %s (webhook_call: %d)',
            $type,
            $problem,
            $this->webhookCall->id,
        )));

        Log::warning('RevenueCat webhook rejected — payload cannot be applied', [
            'type' => $type,
            'problem' => $problem,
            'webhook_call_id' => $this->webhookCall->id,
        ]);
    }

    private function fromMs(?int $ms): ?Carbon
    {
        if (! $ms) {
            return null;
        }

        // Epoch milliseconds are an instant, but Eloquent writes a Carbon in the
        // instance's own zone and reads it back in the app's zone — a UTC instance
        // would shift by the app's offset on every round trip.
        return Carbon::createFromTimestampMs($ms)->setTimezone(config('app.timezone'));
    }
}
