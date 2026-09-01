<?php

namespace App\Services\WorkoutSession;

use App\Enums\PersonalRecordType;
use App\Enums\WorkoutSessionStatus;
use App\Models\SetLog;
use App\Models\WorkoutSession;
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
 * The rules, extracted exactly as they shipped and deliberately not corrected:
 *
 * - Weight and reps are **independent maxima**. The session's heaviest set and
 *   its highest-rep set are compared against the all-time heaviest and all-time
 *   highest-rep sets separately, and those four need not be the same sets. One
 *   heavy single plus one light high-rep set therefore emits two records, and
 *   neither describes a performance anyone achieved in one set.
 * - **No history counts as beaten.** The first time an exercise is logged it
 *   produces a weight record and a reps record with a previous best of 0, so a
 *   first workout of six exercises produces twelve records.
 * - **The session excludes itself from its own history**, by id and not by
 *   status. Detecting twice for the same session therefore returns the same
 *   records twice rather than nothing the second time.
 *
 * Whether those are bugs is a product question, and it is now a question that
 * can be asked: tests/Feature/PersonalRecordDetectionTest.php holds each of
 * them, and changing one means changing the test that says so. Issue 010.
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
     * @param  array{weight?: float|null, reps?: int|null}  $priorBest
     * @return list<PersonalRecord>
     */
    private static function recordsFor(int $exerciseId, Collection $logs, array $priorBest): array
    {
        $exerciseName = $logs->first()->exercise?->name ?? '';

        $sessionWeight = (float) $logs->max(fn (SetLog $log) => (float) $log->weight);
        $sessionReps = (int) $logs->max(fn (SetLog $log) => (int) $log->reps);

        $priorWeight = $priorBest['weight'] ?? null;
        $priorReps = $priorBest['reps'] ?? null;

        $records = [];

        if ($priorWeight === null || $sessionWeight > $priorWeight) {
            $records[] = new PersonalRecord(
                exerciseId: $exerciseId,
                exerciseName: $exerciseName,
                type: PersonalRecordType::Weight,
                previousBest: $priorWeight ?? 0,
                newBest: $sessionWeight,
            );
        }

        if ($priorReps === null || $sessionReps > $priorReps) {
            $records[] = new PersonalRecord(
                exerciseId: $exerciseId,
                exerciseName: $exerciseName,
                type: PersonalRecordType::Reps,
                previousBest: $priorReps ?? 0,
                newBest: $sessionReps,
            );
        }

        return $records;
    }

    /**
     * The athlete's heaviest set and highest-rep set for each exercise, across
     * their completed sessions other than this one.
     *
     * Two maxima from one aggregate, which is what makes them independent: the
     * query cannot say whether they came from the same set, and neither can
     * anything downstream.
     *
     * @param  list<int>  $exerciseIds
     * @return Collection<int, array{weight: float|null, reps: int|null}>
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
                    'weight' => $best->best_weight !== null ? (float) $best->best_weight : null,
                    'reps' => $best->best_reps !== null ? (int) $best->best_reps : null,
                ],
            ]);
    }
}
