<?php

namespace App\Services\Admin;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The super-admin Insights page, Training tab: one answer per question, for
 * one range — the last 7, 30 or 90 days ([now − days, now]).
 *
 * - retention — app users who signed up in the range, and the share of them
 *   with a Completed Session in week 1, 2, 4 and 8 after signup (week N is
 *   days 7(N−1) to 7N−1, SignupWeeks). Only signups whose week N has fully passed count
 *   in that week's denominator (`eligible`), so recent signups don't drag
 *   week 8 to zero; `reached` says whether any signup was old enough.
 * - first_workout — the median hours from signup to the first Completed
 *   Session, over signups in the range that have one, against the same median
 *   for the range before; and every signup in the range bucketed by that
 *   time, "Not yet" being those with no Completed Session.
 *
 * Every people count is of app users (User::appUsers()): staff never count.
 * Training is read through WorkoutSession::completed(), never a second
 * definition. Live queries, cached for CACHE_SECONDS per range — no snapshot
 * tables or scheduled jobs.
 */
final class Insights
{
    public const CACHE_KEY = 'admin.insights';

    public const CACHE_SECONDS = 600;

    /** The ranges the page offers, in days. */
    public const RANGES = [7, 30, 90];

    public const DEFAULT_RANGE = 30;

    /** Weeks after signup that retention reports; week 4 is the headline. */
    public const RETENTION_WEEKS = [1, 2, 4, 8];

    public const HEADLINE_RETENTION_WEEK = 4;

    /**
     * Time-to-first-workout buckets: label => upper bound in hours
     * (exclusive). Signups with no Completed Session go to FIRST_WORKOUT_NOT_YET.
     */
    public const FIRST_WORKOUT_BUCKETS = [
        'Under 1 h' => 1,
        '1–24 h' => 24,
        '1–3 days' => 72,
        '3–7 days' => 168,
        'Over 7 days' => PHP_INT_MAX,
    ];

    public const FIRST_WORKOUT_NOT_YET = 'Not yet';

    /**
     * The range a request asked for, or DEFAULT_RANGE when it is missing or
     * not one of RANGES.
     */
    public static function range(mixed $requested): int
    {
        $days = filter_var($requested, FILTER_VALIDATE_INT);

        return in_array($days, self::RANGES, true) ? $days : self::DEFAULT_RANGE;
    }

    /**
     * @return array{
     *     days: int,
     *     retention: array{
     *         signups: int,
     *         weeks: array<int, array{share: int, retained: int, eligible: int, reached: bool}>,
     *     },
     *     first_workout: array{
     *         signups: int,
     *         median_hours: float|null,
     *         previous_median_hours: float|null,
     *         buckets: list<array{label: string, users: int}>,
     *     },
     * }
     */
    public static function summary(int $days): array
    {
        return Cache::remember(self::CACHE_KEY.'.'.$days, self::CACHE_SECONDS, fn () => self::compute($days));
    }

    private static function compute(int $days): array
    {
        $now = CarbonImmutable::now();
        $from = $now->subDays($days);

        return [
            'days' => $days,
            'retention' => self::retention($from, $now),
            'first_workout' => self::firstWorkout($from, $now, $days),
        ];
    }

    /**
     * @return array{signups: int, median_hours: float|null, previous_median_hours: float|null, buckets: list<array{label: string, users: int}>}
     */
    private static function firstWorkout(CarbonImmutable $from, CarbonImmutable $now, int $days): array
    {
        $hours = self::hoursToFirstWorkout(fn (Builder $users) => $users->whereBetween('users.created_at', [$from, $now]));
        $previous = self::hoursToFirstWorkout(fn (Builder $users) => $users
            ->where('users.created_at', '>=', $from->subDays($days))
            ->where('users.created_at', '<', $from));

        $started = $hours->filter(fn (?float $h) => $h !== null);
        $buckets = array_fill_keys([...array_keys(self::FIRST_WORKOUT_BUCKETS), self::FIRST_WORKOUT_NOT_YET], 0);
        foreach ($hours as $h) {
            $label = $h === null
                ? self::FIRST_WORKOUT_NOT_YET
                : array_key_first(array_filter(self::FIRST_WORKOUT_BUCKETS, fn (int $upTo) => $h < $upTo));
            $buckets[$label]++;
        }

        return [
            'signups' => $hours->count(),
            'median_hours' => self::median($started),
            'previous_median_hours' => self::median($previous->filter(fn (?float $h) => $h !== null)),
            'buckets' => array_map(fn (string $label, int $users) => ['label' => $label, 'users' => $users], array_keys($buckets), $buckets),
        ];
    }

    /**
     * Hours from signup to the first Completed Session for each app user the
     * constraint selects; null when they have none yet.
     *
     * @param  \Closure(Builder<User>): Builder<User>  $signups
     * @return Collection<int, float|null>
     */
    private static function hoursToFirstWorkout(\Closure $signups): Collection
    {
        return $signups(User::query()->appUsers())
            ->withMin(['workoutSessions as first_completed_at' => fn (Builder $sessions) => $sessions->completed()], 'completed_at')
            ->get(['users.id', 'users.created_at'])
            ->map(fn (User $user) => $user->first_completed_at === null
                ? null
                : CarbonImmutable::parse($user->created_at)->diffInSeconds(CarbonImmutable::parse($user->first_completed_at)) / 3600);
    }

    /**
     * @param  Collection<int, float>  $values
     */
    private static function median(Collection $values): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        return round((float) $values->median(), 1);
    }

    /**
     * @return array{signups: int, weeks: array<int, array{share: int, retained: int, eligible: int, reached: bool}>}
     */
    private static function retention(CarbonImmutable $from, CarbonImmutable $now): array
    {
        $signups = User::query()->appUsers()
            ->whereBetween('users.created_at', [$from, $now])
            ->pluck('users.created_at', 'users.id')
            ->map(fn ($createdAt) => CarbonImmutable::parse($createdAt));

        $weeks = [];
        foreach (self::RETENTION_WEEKS as $week) {
            $eligible = $signups->filter(fn (CarbonImmutable $signedUpAt) => SignupWeeks::bounds($signedUpAt, $week)[1] <= $now);
            $retained = SignupWeeks::trainedIn($eligible, $week)->count();

            $weeks[$week] = [
                'share' => $eligible->isNotEmpty() ? (int) round($retained / $eligible->count() * 100) : 0,
                'retained' => $retained,
                'eligible' => $eligible->count(),
                'reached' => $eligible->isNotEmpty(),
            ];
        }

        return ['signups' => $signups->count(), 'weeks' => $weeks];
    }
}
