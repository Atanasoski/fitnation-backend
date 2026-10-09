<?php

namespace App\Services\Admin;

use App\Models\WorkoutSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Weeks counted from each user's own signup: week N is days 7(N−1) to 7N−1
 * after signup, so week 1 is the first seven days and week 2 is days 7–13.
 * The one definition behind the Overview's "trained in week two" and the
 * Insights retention weeks.
 */
final class SignupWeeks
{
    /**
     * Week $week after $signedUpAt as [start, end), end exclusive.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public static function bounds(CarbonImmutable $signedUpAt, int $week): array
    {
        return [$signedUpAt->addDays(7 * ($week - 1)), $signedUpAt->addDays(7 * $week)];
    }

    /**
     * The ids of $signups with a Completed Session in week $week after their
     * own signup. A week's bounds differ per user, so the sessions are read
     * once and placed into weeks here rather than in SQL.
     *
     * @param  Collection<int, CarbonImmutable>  $signups  signup time by user id
     * @return Collection<int, int>
     */
    public static function trainedIn(Collection $signups, int $week): Collection
    {
        if ($signups->isEmpty()) {
            return collect();
        }

        return WorkoutSession::query()
            ->completed()
            ->whereIn('user_id', $signups->keys()->all())
            ->get(['user_id', 'completed_at'])
            ->filter(function (WorkoutSession $session) use ($signups, $week) {
                [$start, $end] = self::bounds($signups[$session->user_id], $week);

                return $session->completed_at >= $start && $session->completed_at < $end;
            })
            ->pluck('user_id')
            ->unique()
            ->values();
    }
}
