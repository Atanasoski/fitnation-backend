<?php

namespace App\Services\Admin;

use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use App\Services\WorkoutSession\SetOwnership;
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
 * - generator — sessions created in the range, generated
 *   (`is_auto_generated`) against the rest: each group's size and the % that
 *   are Completed Sessions, swapped, or cancelled. Swapped is a cancelled
 *   session another one points to through `replaced_session_id` — what
 *   WorkoutGenerationService::regenerateSession leaves behind — so "didn't
 *   like it" is not mixed with "gave up"; cancelled is the rest of the
 *   cancels. Drafts and active sessions count in the size only.
 * - skipped — over the exercise rows of Completed Sessions completed in the
 *   range: per exercise, how often it was included and how often its row has
 *   no set log (SetOwnership decides which sets a row owns). Exercises
 *   included fewer than SKIPPED_MIN_INCLUDED times are left out; the top
 *   SKIPPED_TOP by rate are returned.
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
     * Fewest inclusions in the range for an exercise to rank as skipped, so
     * one-offs (one user, one bad day) don't top the list.
     */
    public const SKIPPED_MIN_INCLUDED = 100;

    /** How many of the most-skipped exercises the card lists. */
    public const SKIPPED_TOP = 10;

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
     *     generator: array{
     *         generated: array{sessions: int, completed: int, swapped: int, cancelled: int},
     *         other: array{sessions: int, completed: int, swapped: int, cancelled: int},
     *     },
     *     skipped: list<array{exercise_id: int, name: string, included: int, skipped: int, rate: int}>,
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
            'generator' => self::generator($from, $now),
            'skipped' => self::skipped($from, $now),
        ];
    }

    /**
     * @return array{generated: array{sessions: int, completed: int, swapped: int, cancelled: int}, other: array{sessions: int, completed: int, swapped: int, cancelled: int}}
     */
    private static function generator(CarbonImmutable $from, CarbonImmutable $now): array
    {
        $sessions = WorkoutSession::query()
            ->whereIn('workout_sessions.user_id', User::query()->appUsers()->select('users.id'))
            ->whereBetween('workout_sessions.created_at', [$from, $now]);

        $cancelled = $sessions->clone()->where('workout_sessions.status', WorkoutSessionStatus::Cancelled);
        $swapped = $cancelled->clone()->whereExists(fn ($replacement) => $replacement
            ->from('workout_sessions', 'replacements')
            ->whereColumn('replacements.replaced_session_id', 'workout_sessions.id'));

        $counts = fn (Builder $query) => $query->clone()
            ->toBase()
            ->selectRaw('workout_sessions.is_auto_generated as auto, count(*) as sessions')
            ->groupBy('workout_sessions.is_auto_generated')
            ->pluck('sessions', 'auto')
            ->mapWithKeys(fn ($n, $auto) => [(int) (bool) $auto => (int) $n]);

        $all = $counts($sessions);
        $done = $counts($sessions->clone()->completed());
        $swaps = $counts($swapped);
        $cancels = $counts($cancelled);

        $group = function (int $generated) use ($all, $done, $swaps, $cancels) {
            $total = $all->get($generated, 0);
            $share = fn (int $n) => $total > 0 ? (int) round($n / $total * 100) : 0;

            return [
                'sessions' => $total,
                'completed' => $share($done->get($generated, 0)),
                'swapped' => $share($swaps->get($generated, 0)),
                'cancelled' => $share($cancels->get($generated, 0) - $swaps->get($generated, 0)),
            ];
        };

        return ['generated' => $group(1), 'other' => $group(0)];
    }

    /**
     * @return list<array{exercise_id: int, name: string, included: int, skipped: int, rate: int}>
     */
    private static function skipped(CarbonImmutable $from, CarbonImmutable $now): array
    {
        $sessions = WorkoutSession::query()
            ->completed()
            ->whereIn('workout_sessions.user_id', User::query()->appUsers()->select('users.id'))
            ->whereBetween('workout_sessions.completed_at', [$from, $now])
            ->select('workout_sessions.id');

        $unlogged = SetOwnership::constrainToOuterRow(SetLog::query()->toBase()->selectRaw('1'))->toSql();

        $rows = WorkoutSessionExercise::query()
            ->toBase()
            ->whereIn('workout_session_exercises.workout_session_id', $sessions)
            ->groupBy('workout_session_exercises.exercise_id')
            ->havingRaw('count(*) >= ?', [self::SKIPPED_MIN_INCLUDED])
            ->selectRaw('workout_session_exercises.exercise_id, count(*) as included')
            ->selectRaw("sum(case when not exists ({$unlogged}) then 1 else 0 end) as skipped")
            ->orderByRaw('skipped / included desc')
            ->orderBy('workout_session_exercises.exercise_id')
            ->limit(self::SKIPPED_TOP)
            ->get();

        $names = Exercise::query()->whereIn('id', $rows->pluck('exercise_id'))->pluck('name', 'id');

        return $rows->map(fn (object $row) => [
            'exercise_id' => (int) $row->exercise_id,
            'name' => (string) $names->get($row->exercise_id),
            'included' => (int) $row->included,
            'skipped' => (int) $row->skipped,
            'rate' => (int) round($row->skipped / $row->included * 100),
        ])->all();
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
