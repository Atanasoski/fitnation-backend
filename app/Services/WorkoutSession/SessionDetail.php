<?php

namespace App\Services\WorkoutSession;

use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Support\Collection;

/**
 * A workout session resolved for the athlete performing it: its exercises, the
 * sets logged against each, what they did last time, and what they are aiming
 * for.
 *
 * Loads everything it needs itself. That is the point of the module: the shape
 * it replaces required its caller to have eager-loaded both
 * workoutSessionExercises and setLogs, and silently produced an empty exercise
 * list when they had not — which is exactly what GET /workout-sessions/today
 * did. A precondition nobody can violate beats a precondition everybody
 * remembers.
 *
 * Callers must NOT pre-load: this reloads the relations on every call so a
 * session read that follows a write cannot serve a stale copy.
 *
 * Everything here is in Canonical Units. Conversion for a Unit System happens
 * at the HTTP boundary (ADR-0001).
 */
final class SessionDetail
{
    /**
     * @param  Collection<int, SessionExerciseDetail>  $exercises
     */
    private function __construct(
        private WorkoutSession $session,
        private Collection $exercises,
    ) {}

    public static function for(WorkoutSession $session, ?User $user): self
    {
        $session->load(WorkoutSession::detailRelations());

        $rows = $session->workoutSessionExercises;
        $setLogs = $session->setLogs;

        $progressions = app(SessionProgression::class);
        $progression = $progressions->forRows($rows, $user);
        $previousSetLogs = $session->getPreviousSetLogsForExercises(
            $rows->pluck('exercise_id')->all()
        );

        // Exercises appearing on more than one row: legacy sets carrying no row
        // id cannot be attributed to either, so they are not shown under both.
        $duplicatedExerciseIds = $rows
            ->groupBy('exercise_id')
            ->filter(fn ($group) => $group->count() > 1)
            ->keys();

        $exercises = $rows->map(function ($row) use (
            $user,
            $setLogs,
            $progressions,
            $progression,
            $previousSetLogs,
            $duplicatedExerciseIds
        ) {
            $resolved = $progression[$row->exercise_id]
                ?? $progressions->withoutUser($row->exercise);

            $targets = $progressions->targetsFor($row, $resolved['targets']);

            $loggedSets = $row->ownedSetsFrom(
                $setLogs,
                ! $duplicatedExerciseIds->contains($row->exercise_id)
            );

            return new SessionExerciseDetail(
                row: $row,
                targets: $targets,
                lastPerformance: $resolved['last_performance'],
                loggedSets: $loggedSets,
                previousSets: $previousSetLogs->get($row->exercise_id, collect()),
                // Deliberately the STORED target_sets, not the resolved one.
                // The two can disagree, which is a real inconsistency — see
                // docs/issues — but changing it here would silently move every
                // existing session's progress percentage.
                isCompleted: $loggedSets->count() >= ($row->target_sets ?? 3),
                progressionStatus: $user === null
                    ? 'no_history'
                    : $progressions->statusFor(
                        $resolved['last_performance'],
                        (int) ($targets['min_target_reps'] ?? 0),
                        (int) ($targets['max_target_reps'] ?? 0),
                        $row->exercise
                    ),
            );
        })->values();

        return new self($session, $exercises);
    }

    public function session(): WorkoutSession
    {
        return $this->session;
    }

    /**
     * @return Collection<int, SessionExerciseDetail>
     */
    public function exercises(): Collection
    {
        return $this->exercises;
    }

    /**
     * @return array{total_exercises: int, completed_exercises: int, progress_percent: float|int}
     */
    public function progress(): array
    {
        $total = $this->exercises->count();
        $completed = $this->exercises->filter(fn (SessionExerciseDetail $e) => $e->isCompleted)->count();

        return [
            'total_exercises' => $total,
            'completed_exercises' => $completed,
            'progress_percent' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
        ];
    }
}
