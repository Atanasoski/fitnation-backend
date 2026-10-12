<?php

namespace App\Services\Subscription;

/**
 * The accounts whose sandbox purchases production applies: the user IDs in
 * subscriptions.sandbox_user_ids, App Review's. Every other sandbox purchase
 * is ignored in production by the webhook and the sync alike; outside
 * production nothing is filtered, so this is only asked there.
 */
final class SandboxAllowList
{
    public static function isEmpty(): bool
    {
        return self::ids() === [];
    }

    /** True when there is at least one ID and every one of them is listed. */
    public static function covers(int ...$userIds): bool
    {
        return $userIds !== [] && array_diff($userIds, self::ids()) === [];
    }

    /** @return array<int, int> */
    private static function ids(): array
    {
        return config('subscriptions.sandbox_user_ids', []);
    }
}
