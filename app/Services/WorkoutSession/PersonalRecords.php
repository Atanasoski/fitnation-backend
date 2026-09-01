<?php

namespace App\Services\WorkoutSession;

use App\Enums\PersonalRecordType;
use App\Enums\WorkoutSessionStatus;
use App\Models\SetLog;
use App\Models\WorkoutSession;
use App\Services\FitnessMetrics\StrengthScore;
use Illuminate\Support\Collection;

/**
 * What a session beat: the personal records its sets set against everything the
 * athlete had logged for the same exercises before.
 *
 * This is a read. It writes nothing and completes nothing — a caller decides
 * whether a session is finished and persists that itself, then asks here what
 * the session was worth. That separation is the point of the module: the rules
 * below used to be reachable only by issuing a completed HTTP request, so
 * nobody could try a case against them.
 *
 * The rules:
 *
 * - **A record describes one set.** Per exercise, the sets that beat a prior
 *   best are gathered and the best single one among them — by estimated 1RM,
 *   not by load — is the only one judged. A heavy single and a light high-rep
 *   set are two performances, and only the better one is celebrated.
 * - **No history records nothing.** An exercise with nothing logged before it
 *   has no best to beat, so a first-ever session is silent.
 * - **The session excludes itself from its own history**, by id and not by
 *   status. Detecting twice for the same session therefore returns the same
 *   records twice rather than nothing the second time. Issue 003.
 *
 * Records are in Canonical Units (ADR-0001).
 */
final class PersonalRecords
{
    /**
     * The records the session's sets set, in session-exercise order, weight
     * before reps within each exercise.
     *
     * @return Collection<int, PersonalRecord>
     */
    public static function detect(WorkoutSession $session): Collection
    {
        $sets = SetLog::query()
            ->where('workout_session_id', $session->id)
            ->with('exercise:id,name')
            ->get();

        $priorBests = self::priorBests($session, $sets->pluck('exercise_id')->unique()->values()->all());

        $records = collect();

        foreach ($sets->groupBy('exercise_id') as $exerciseId => $logs) {
            $records = $records->concat(
                self::recordsFor((int) $exerciseId, $logs, $priorBests->get((int) $exerciseId, []))
            );
        }

        return $records->values();
    }

    /**
     * Sets logged against one exercise in one session, judged against that
     * exercise's prior bests.
     *
     * @param  Collection<int, SetLog>  $logs
     * @param  array{weight: float, reps: int}|array{}  $priorBest
     * @return list<PersonalRecord>
     */
    private static function recordsFor(int $exerciseId, Collection $logs, array $priorBest): array
    {
        if ($priorBest === []) {
            return [];
        }

        $exerciseName = $logs->first()->exercise?->name ?? '';

        return $logs
            ->sortByDesc(fn (SetLog $log) => StrengthScore::oneRepMax((float) $log->weight, (int) $log->reps))
            ->map(fn (SetLog $log) => self::recordsFrom(
                $exerciseId, $exerciseName, $log, $priorBest['weight'], $priorBest['reps']
            ))
            ->first(fn (array $records) => $records !== [], []);
    }

    /**
     * What one set beat, which is nothing at all unless it beat a prior best.
     *
     * A set qualifies as record-setting exactly when this is non-empty, so the
     * two questions cannot drift apart: recordsFor() sorts the exercise's sets
     * by estimated 1RM and takes the first set this answers for.
     *
     * Estimated 1RM rather than load, so that a session's best set is the best
     * set and not merely its heaviest — against a 100 × 12 history, 100 × 20
     * beats 101 × 1 and deserves the rep record it earned. Sets tying on 1RM
     * are left in the order the query returned them; nothing depends on which.
     *
     * @return list<PersonalRecord>
     */
    private static function recordsFrom(
        int $exerciseId,
        string $exerciseName,
        SetLog $log,
        float $priorWeight,
        int $priorReps,
    ): array {
        $weight = (float) $log->weight;
        $reps = (int) $log->reps;

        $records = [];

        if ($weight > $priorWeight) {
            $records[] = new PersonalRecord(
                exerciseId: $exerciseId,
                exerciseName: $exerciseName,
                type: PersonalRecordType::Weight,
                previousBest: $priorWeight,
                newBest: $weight,
            );
        }

        if ($reps > $priorReps) {
            $records[] = new PersonalRecord(
                exerciseId: $exerciseId,
                exerciseName: $exerciseName,
                type: PersonalRecordType::Reps,
                previousBest: $priorReps,
                newBest: $reps,
            );
        }

        return $records;
    }

    /**
     * The athlete's heaviest set and highest-rep set for each exercise, across
     * their completed sessions other than this one.
     *
     * Two maxima from one aggregate over the same rows: an exercise is either
     * absent — no history at all, and so nothing to beat — or has both.
     *
     * @param  list<int>  $exerciseIds
     * @return Collection<int, array{weight: float, reps: int}>
     */
    private static function priorBests(WorkoutSession $session, array $exerciseIds): Collection
    {
        if ($exerciseIds === []) {
            return collect();
        }

        return SetLog::query()
            ->whereIn('exercise_id', $exerciseIds)
            ->whereHas('workoutSession', fn ($query) => $query
                ->where('user_id', $session->user_id)
                ->where('status', WorkoutSessionStatus::Completed)
                ->where('id', '!=', $session->id)
            )
            ->selectRaw('exercise_id, MAX(weight) as best_weight, MAX(reps) as best_reps')
            ->groupBy('exercise_id')
            ->get()
            ->mapWithKeys(fn (SetLog $best) => [
                (int) $best->exercise_id => [
                    'weight' => (float) $best->best_weight,
                    'reps' => (int) $best->best_reps,
                ],
            ]);
    }
}
