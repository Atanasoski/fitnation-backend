<?php

namespace App\Webhooks\RevenueCat;

use Illuminate\Database\Eloquent\Builder;
use Spatie\WebhookClient\Models\WebhookCall;

/**
 * RevenueCat webhook calls whose processing failed (the exception is set),
 * and replaying them. Shared by `revenuecat:replay-failed` and the System
 * page so the two cannot drift.
 */
final class FailedWebhookCalls
{
    /**
     * @return Builder<WebhookCall>
     */
    public static function query(): Builder
    {
        return WebhookCall::query()
            ->where('name', 'revenuecat')
            ->whereNotNull('exception');
    }

    /**
     * Clear the prior failure and dispatch the processing job again. The job
     * re-records the exception via failed() if it fails again; its
     * idempotency guards make replay safe.
     */
    public static function replay(WebhookCall $call): void
    {
        $call->update(['exception' => null]);
        ProcessRevenueCatWebhook::dispatch($call);
    }
}
