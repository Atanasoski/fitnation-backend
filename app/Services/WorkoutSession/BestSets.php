<?php

namespace App\Services\WorkoutSession;

use App\Enums\WorkoutSessionStatus;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\FitnessMetrics\StrengthScore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The best set a user has ever logged on each exercise, before the session
 * being looked at. "Best" is the set with the highest estimated one-rep max —
 * the yardstick PersonalRecords already uses to pick a set — with reps
 * deciding a tie (bodyweight, where every weight is zero) and the most recent
 * one after that. One query for the whole session (or user), in Canonical
 * Units.
 */
final class BestSets
{
    /**
     * @param  array<int, int>  $exerciseIds
     * @return Collection<int, array{weight: float, reps: int, performed_at: string}> keyed by exercise id
     */
    public static function forSession(WorkoutSession $session, array $exerciseIds): Collection
    {
        if ($exerciseIds === [] || $session->user_id === null) {
            return collect();
        }

        $logs = (new SetLog)->getTable();
        $sessions = (new WorkoutSession)->getTable();

        $rows = SetLog::query()
            ->join($sessions, "{$sessions}.id", '=', "{$logs}.workout_session_id")
            ->where("{$sessions}.user_id", $session->user_id)
            ->where("{$sessions}.status", WorkoutSessionStatus::Completed->value)
            ->where("{$sessions}.id", '!=', $session->id)
            ->whereIn("{$logs}.exercise_id", $exerciseIds)
            ->get([
                "{$logs}.exercise_id",
                "{$logs}.weight",
                "{$logs}.reps",
                "{$sessions}.performed_at as session_performed_at",
            ]);

        return $rows
            ->groupBy('exercise_id')
            ->map(fn (Collection $logsForExercise) => self::shape(self::best($logsForExercise)));
    }

    /**
     * The best set the user has ever logged on each exercise, across their
     * Completed Sessions, the most recently set first: what the admin user
     * page shows under Personal Records, the bests those records moved.
     *
     * @return Collection<int, array{exercise: string, weight: float, reps: int, performed_at: string}> keyed by exercise id
     */
    public static function forUser(User $user, int $limit = 10): Collection
    {
        $logs = (new SetLog)->getTable();
        $sessions = (new WorkoutSession)->getTable();

        $rows = SetLog::query()
            ->join($sessions, "{$sessions}.id", '=', "{$logs}.workout_session_id")
            ->where("{$sessions}.user_id", $user->id)
            ->where("{$sessions}.status", WorkoutSessionStatus::Completed->value)
            ->with('exercise:id,name')
            ->get([
                "{$logs}.exercise_id",
                "{$logs}.weight",
                "{$logs}.reps",
                "{$sessions}.performed_at as session_performed_at",
            ]);

        return $rows
            ->groupBy('exercise_id')
            ->map(function (Collection $logsForExercise) {
                $best = self::best($logsForExercise);

                return ['exercise' => $best->exercise?->name ?? ''] + self::shape($best);
            })
            ->sortByDesc('performed_at')
            ->take($limit);
    }

    /**
     * @param  Collection<int, SetLog>  $logs
     */
    private static function best(Collection $logs): SetLog
    {
        return $logs->sortByDesc(fn (SetLog $log) => [
            StrengthScore::oneRepMax((float) $log->weight, (int) $log->reps),
            (int) $log->reps,
            (string) $log->getAttribute('session_performed_at'),
        ])->first();
    }

    /**
     * @return array{weight: float, reps: int, performed_at: string}
     */
    private static function shape(SetLog $best): array
    {
        return [
            'weight' => (float) $best->weight,
            'reps' => (int) $best->reps,
            'performed_at' => Carbon::parse($best->getAttribute('session_performed_at'))->toJSON(),
        ];
    }
}
