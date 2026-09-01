<?php

namespace App\Services\WorkoutSession;

use App\Models\WorkoutSessionExercise;
use Illuminate\Support\Collection;

/**
 * One exercise of a Session Detail, fully resolved: the row, the targets the
 * user is working to, the sets they have logged against it, what they did last
 * time, and whether they are done with it.
 *
 * $targets is already resolved — the precedence between a stored value and the
 * calculated one has been applied, so a reader never decides which wins. It is
 * in Canonical Units (ADR-0001); formatting for a Unit System happens at the
 * HTTP boundary, not here.
 */
final readonly class SessionExerciseDetail
{
    /**
     * @param  array<string, mixed>  $targets  progression_mode, target_sets,
     *                                         min_target_reps, max_target_reps, target_weight,
     *                                         total_reps_previous, total_reps_target, rest_seconds
     * @param  array<string, mixed>|null  $lastPerformance
     * @param  Collection<int, \App\Models\SetLog>  $loggedSets
     * @param  Collection<int, \App\Models\SetLog>  $previousSets
     */
    public function __construct(
        public WorkoutSessionExercise $row,
        public array $targets,
        public ?array $lastPerformance,
        public Collection $loggedSets,
        public Collection $previousSets,
        public bool $isCompleted,
        public string $progressionStatus,
    ) {}
}
