<?php

namespace App\Services\Subscription;

use RuntimeException;

/**
 * A {@see SubscriptionSync} that could not read RevenueCat, so wrote nothing.
 * $reason is the machine-readable code the API answers with, $status its HTTP status.
 */
final class SubscriptionSyncFailed extends RuntimeException
{
    private function __construct(string $message, public readonly string $reason, public readonly int $status)
    {
        parent::__construct($message);
    }

    /** The RevenueCat secret API key is not set: an operator error. */
    public static function notConfigured(): self
    {
        return new self('Subscription sync is not configured.', 'subscription_sync_not_configured', 500);
    }

    /** RevenueCat could not be reached or answered an error. */
    public static function upstream(): self
    {
        return new self('Could not reach the subscription provider.', 'subscription_sync_failed', 502);
    }
}
