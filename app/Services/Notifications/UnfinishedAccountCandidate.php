<?php

namespace App\Services\Notifications;

use App\Models\User;

/**
 * One Unfinished Account the rule says is due a nudge right now: which step of
 * the ladder they have reached, and what they are stuck at as of this instant —
 * 'unverified' (email not yet confirmed) or 'not_onboarded' (confirmed, but
 * onboarding unfinished). The content of the nudge follows stuckAt.
 */
final readonly class UnfinishedAccountCandidate
{
    public const UNVERIFIED = 'unverified';

    public const NOT_ONBOARDED = 'not_onboarded';

    public function __construct(
        public User $user,
        public int $step,
        public string $stuckAt,
    ) {}

    public static function stuckAt(User $user): string
    {
        return $user->hasVerifiedEmail() ? self::NOT_ONBOARDED : self::UNVERIFIED;
    }
}
