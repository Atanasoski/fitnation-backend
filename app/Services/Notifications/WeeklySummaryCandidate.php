<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Services\FitnessMetrics\WeeklyProgress;
use Carbon\CarbonImmutable;

/**
 * One user the Weekly Summary rule says is due right now, with the instant
 * that is "now" on their own clock — their local Monday — and, on demand, the
 * Weekly Progress numbers read as of it.
 *
 * The numbers are read once per candidate and kept, so the rule itself stays
 * at a constant number of queries and the caller that sends the mail pays for
 * the numbers only for the users it sends to.
 */
final class WeeklySummaryCandidate
{
    /** @var ?array<string, mixed> */
    private ?array $progress = null;

    public function __construct(
        public readonly User $user,
        public readonly CarbonImmutable $asOf,
    ) {}

    /**
     * The Weekly Progress payload for the week that ended on this Monday, in
     * Canonical Units (ADR-0001).
     *
     * @return array<string, mixed>
     */
    public function progress(): array
    {
        return $this->progress ??= (new WeeklyProgress)->for($this->user, $this->asOf);
    }
}
