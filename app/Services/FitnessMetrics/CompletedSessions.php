<?php

namespace App\Services\FitnessMetrics;

use App\Enums\WorkoutSessionStatus;
use App\Models\WorkoutSession;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The Completed Sessions a fitness metric is allowed to see, and the set logs
 * inside them.
 *
 * The only place in the metrics code that decides what "completed" means. It
 * used to be decided twice and differently — a user's own score read
 * `completed_at IS NOT NULL`, the percentile cohort they were ranked against
 * read `status = completed` — so a session that was one and not the other
 * counted for one and not the other. The answer is `status = completed`,
 * because that is what completing a session writes
 * (Api\WorkoutSessionController::complete) and what every other consumer in the
 * codebase already reads.
 *
 * Everything here is in Canonical Units (ADR-0001).
 */
final class CompletedSessions
{
    /**
     * How far back "recent" reaches for every fitness metric. One number,
     * because the three metrics are read side by side in one payload and a
     * user comparing them is entitled to assume they cover the same days.
     */
    public const RECENT_DAYS = 30;

    /**
     * Completed sessions for one user. No date bound — the caller adds theirs.
     */
    public static function sessions(int $userId): EloquentBuilder
    {
        return WorkoutSession::query()
            ->where('user_id', $userId)
            ->where('status', WorkoutSessionStatus::Completed);
    }

    /**
     * How many completed sessions a user performed since a date. The bar a
     * cohort member has to clear before their numbers are worth ranking.
     */
    public static function sessionCountSince(int $userId, Carbon $from): int
    {
        return self::sessions($userId)
            ->where('performed_at', '>=', $from)
            ->count();
    }

    /**
     * Every set log that counts toward a metric, for one user, in a window.
     *
     * Rows carry `weight`, `reps`, `performed_at`, `exercise_name` and
     * `muscle_group_name`. Sets with no load, no reps, or no primary muscle
     * group are not measurable and are excluded here rather than by each caller.
     *
     * `$to` is exclusive so adjacent windows — the last 30 days and the 30
     * before that — cannot both claim the same set.
     */
    public static function setLogs(int $userId, ?Carbon $from = null, ?Carbon $to = null): QueryBuilder
    {
        $query = DB::table('workout_session_set_logs')
            ->join('workout_sessions', 'workout_session_set_logs.workout_session_id', '=', 'workout_sessions.id')
            ->join('workout_exercises', 'workout_session_set_logs.exercise_id', '=', 'workout_exercises.id')
            ->join('exercise_muscle_group', 'workout_exercises.id', '=', 'exercise_muscle_group.exercise_id')
            ->join('muscle_groups', 'exercise_muscle_group.muscle_group_id', '=', 'muscle_groups.id')
            ->where('workout_sessions.user_id', $userId)
            ->where('workout_sessions.status', WorkoutSessionStatus::Completed)
            ->whereNotNull('workout_session_set_logs.weight')
            ->where('workout_session_set_logs.weight', '>', 0)
            ->where('workout_session_set_logs.reps', '>', 0)
            ->where('exercise_muscle_group.is_primary', true)
            ->select([
                'workout_session_set_logs.*',
                'workout_sessions.performed_at',
                'workout_exercises.name as exercise_name',
                'muscle_groups.name as muscle_group_name',
            ]);

        if ($from) {
            $query->where('workout_sessions.performed_at', '>=', $from);
        }

        if ($to) {
            $query->where('workout_sessions.performed_at', '<', $to);
        }

        return $query;
    }

    /**
     * Training volume (weight × reps) per muscle group over a window, keyed by
     * the muscle group's name as stored.
     *
     * @return array<string, float>
     */
    public static function volumeByMuscleGroup(int $userId, Carbon $from, ?Carbon $to = null): array
    {
        $volumes = [];

        foreach (self::setLogs($userId, $from, $to)->get() as $set) {
            $volumes[$set->muscle_group_name] ??= 0;
            $volumes[$set->muscle_group_name] += $set->weight * $set->reps;
        }

        return $volumes;
    }
}
