<?php

namespace App\Services\WorkoutSession;

use App\Models\Exercise;
use App\Models\User;
use App\Services\WorkoutGenerator\ProgressionCalculatorService;

/**
 * What a user should be aiming for on the exercises of a workout session: their
 * targets, and the last performance those targets were derived from.
 *
 * Both shapes the callers need live here — a whole session's rows resolved in
 * one history query, and a single row resolved on its own — so the batched and
 * per-row answers cannot drift apart. Previously the batched form was a static
 * on WorkoutSessionExerciseResource, which put a history query behind an HTTP
 * serializer and left anything outside the HTTP layer unable to ask the
 * question at all.
 *
 * Everything here speaks Canonical Units. Conversion belongs at the HTTP
 * boundary (ADR-0001) and this module has no idea unit systems exist.
 */
class SessionProgression
{
    public function __construct(private ProgressionCalculatorService $calculator) {}

    /**
     * Resolve progression for a set of session-exercise rows in one batch: one
     * history query for the whole set instead of one — in fact two — per row.
     *
     * Keyed by exercise id, so a session carrying the same exercise on several
     * rows resolves it once. Empty when there is no user to calculate against.
     *
     * @param  iterable<int, \App\Models\WorkoutSessionExercise>  $sessionExercises
     * @return array<int, array{targets: array<string, mixed>, last_performance: array<string, mixed>|null}>
     */
    public function forRows(iterable $sessionExercises, ?User $user): array
    {
        if (! $user) {
            return [];
        }

        $exercises = collect($sessionExercises)
            ->map(fn ($sessionExercise) => $sessionExercise->exercise)
            ->filter()
            ->unique('id');

        if ($exercises->isEmpty()) {
            return [];
        }

        $experience = $user->profile?->training_experience;
        $lastPerformances = $this->calculator->getLastPerformanceForExercises($exercises, $user);

        $progression = [];

        foreach ($exercises as $exercise) {
            $lastPerformance = $lastPerformances[$exercise->id] ?? null;

            $progression[$exercise->id] = [
                'targets' => $this->calculator->calculateTargetsFrom($exercise, $user, $experience, $lastPerformance),
                'last_performance' => $lastPerformance,
            ];
        }

        return $progression;
    }

    /**
     * Resolve one exercise's progression on its own. Costs a history lookup, so
     * this is for endpoints that genuinely serialize a single row — never for a
     * loop over a session's rows, which is what forRows() exists for.
     *
     * @return array{targets: array<string, mixed>, last_performance: array<string, mixed>|null}
     */
    public function forExercise(Exercise $exercise, User $user): array
    {
        $lastPerformance = $this->calculator->getLastPerformance($exercise, $user);

        return [
            'targets' => $this->calculator->calculateTargetsFrom(
                $exercise,
                $user,
                $user->profile?->training_experience,
                $lastPerformance
            ),
            'last_performance' => $lastPerformance,
        ];
    }

    /**
     * Targets used when there is no authenticated user to calculate against.
     *
     * @return array{targets: array<string, mixed>, last_performance: null}
     */
    public function withoutUser(?Exercise $exercise): array
    {
        return [
            'targets' => [
                'progression_mode' => 'double_progression',
                'target_sets' => 3,
                'min_target_reps' => 8,
                'max_target_reps' => 12,
                'target_weight' => 0,
                'total_reps_previous' => null,
                'total_reps_target' => null,
                'rest_seconds' => $exercise->default_rest_sec ?? 90,
            ],
            'last_performance' => null,
        ];
    }

    /**
     * Which target a user actually sees, per CONTEXT.md.
     *
     * A stored sets/reps/rest value wins when it holds anything; target_weight
     * never does, because a Session Target Weight is recomputed on every read
     * from the user's latest completed session. In total_reps mode the rep
     * bounds are meaningless and are suppressed.
     *
     * Per-row rather than part of forRows(), which keys by exercise id: two rows
     * carrying the same exercise share a calculation but resolve separately.
     *
     * @param  \App\Models\WorkoutSessionExercise  $row
     * @param  array<string, mixed>  $calculated
     * @return array<string, mixed>
     */
    public function targetsFor($row, array $calculated): array
    {
        $targets = [
            'progression_mode' => $calculated['progression_mode'],
            'target_sets' => $row->target_sets ?: $calculated['target_sets'],
            'min_target_reps' => $row->min_target_reps ?: $calculated['min_target_reps'],
            'max_target_reps' => $row->max_target_reps ?: $calculated['max_target_reps'],
            'target_weight' => $calculated['target_weight'],
            'total_reps_previous' => $calculated['total_reps_previous'],
            'total_reps_target' => $calculated['total_reps_target'],
            'rest_seconds' => $row->rest_seconds ?: $calculated['rest_seconds'],
        ];

        if (($calculated['progression_mode'] ?? 'double_progression') === 'total_reps') {
            $targets['min_target_reps'] = null;
            $targets['max_target_reps'] = null;
        }

        return $targets;
    }

    /**
     * Where the user stands against the rep range they are working in.
     *
     * Takes the *resolved* rep bounds, not the stored ones, because the bounds
     * a user is judged against are the ones they were shown.
     *
     * @param  array<string, mixed>|null  $lastPerformance
     */
    public function statusFor(?array $lastPerformance, int $minReps, int $maxReps, ?Exercise $exercise): string
    {
        return $this->calculator->getProgressionStatus($lastPerformance, $minReps, $maxReps, $exercise);
    }
}
