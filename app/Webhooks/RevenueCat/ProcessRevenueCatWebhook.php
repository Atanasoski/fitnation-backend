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

        if (($event['type'] ?? null) === 'TEST') {
            Log::info('RevenueCat TEST event received — skipping', ['webhook_call_id' => $this->webhookCall->id]);

            return;
        }

        $user = User::find($event['app_user_id'] ?? null);

        if (! $user) {
            // Throw so the queue retries — the webhook payload is safely stored
            // in webhook_calls and won't be lost.
            throw new RuntimeException(sprintf(
                'RevenueCat webhook for unknown user "%s" (event: %s, webhook_call: %d)',
                $event['app_user_id'] ?? 'null',
                $event['type'],
                $this->webhookCall->id,
            ));
        }

        DB::transaction(function () use ($user, $event) {
            match ($event['type']) {
                'INITIAL_PURCHASE' => $this->handleInitialPurchase($user, $event),
                'RENEWAL', 'PRODUCT_CHANGE', 'PRICE_CHANGE' => $this->handleRenewal($user, $event),
                'CANCELLATION' => $this->handleCancellation($user),
                'UNCANCELLATION', 'SUBSCRIPTION_RESUMED' => $this->handleUncancellation($user),
                'EXPIRATION' => $this->handleExpiration($user),
                'BILLING_ISSUE' => $this->handleBillingIssue($user),
                'SUBSCRIPTION_PAUSED' => $this->handlePaused($user),
                'TRANSFER' => $this->handleTransfer($event),
                default => Log::info('Unhandled RevenueCat event type', [
                    'type' => $event['type'],
                    'webhook_call_id' => $this->webhookCall->id,
                    'event' => $event,
                ]),
            };
        });
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

    private function handleCancellation(User $user): void
    {
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

    // RevenueCat TRANSFER moves a subscriber between app_user_ids.
    // Throw so the job fails visibly — silently completing would mark it "processed"
    // while the subscription ownership is actually unresolved.
    private function handleTransfer(array $event): void
    {
        throw new RuntimeException(sprintf(
            'RevenueCat TRANSFER requires manual review (from: %s, to: %s, webhook_call: %d)',
            implode(',', (array) ($event['transferred_from'] ?? [])),
            implode(',', (array) ($event['transferred_to'] ?? [])),
            $this->webhookCall->id,
        ));
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
