<?php

namespace App\Services\Notifications;

use App\Models\User;

/**
 * One user the Inactivity rule says is due a nudge right now, and which step
 * of the ladder they have reached.
 */
final readonly class InactivityNudgeCandidate
{
    public function __construct(
        public User $user,
        public int $step,
    ) {}
}
