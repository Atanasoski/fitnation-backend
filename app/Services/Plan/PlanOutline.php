<?php

namespace App\Services\Plan;

use App\Models\Plan;
use App\Models\User;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Support\Collection;

/**
 * A user's plans as the outline page shows them: every Program and Routine,
 * each with its workouts and their exercise rows, and the plan, workout and
 * row a person has selected (023/06, 023/07).
 *
 * The selection comes from the URL, so it is only a hint: an id that is not
 * one of this user's plans (a stale link, a plan just deleted) falls back to
 * the first plan rather than failing, and a workout or row that is not under
 * the selected plan is not selected. The tree lists active plans first.
 *
 * Loads everything it shows; callers pass the user and the requested id.
 */
final class PlanOutline
{
    /**
     * @param  Collection<int, Plan>  $plans
     */
    private function __construct(
        public readonly Collection $plans,
        public readonly ?Plan $plan,
        public readonly ?WorkoutTemplate $workout = null,
        public readonly ?WorkoutTemplateExercise $row = null,
    ) {}

    public static function for(User $user, ?int $planId = null, ?int $workoutId = null, ?int $rowId = null): self
    {
        $plans = Plan::query()
            ->where('user_id', $user->id)
            ->with(['workoutTemplates' => fn ($query) => $query
                ->orderBy('week_number')
                ->orderByRaw('day_of_week IS NULL')
                ->orderBy('day_of_week')
                ->orderBy('order_index')
                ->orderBy('id'),
                // Ties in row order break by id, as WorkoutRowOrder does.
                'workoutTemplates.workoutTemplateExercises' => fn ($query) => $query->orderBy('id'),
                'workoutTemplates.workoutTemplateExercises.exercise',
            ])
            ->orderByDesc('is_active')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $selected = $plans->firstWhere('id', $planId) ?? $plans->first();
        $workout = $selected?->workoutTemplates->firstWhere('id', $workoutId);
        $row = $workout?->workoutTemplateExercises->firstWhere('id', $rowId);

        return new self($plans, $selected, $workout, $row);
    }

    /**
     * The plan activating the selected one would deactivate (ADR-0002).
     */
    public function replaces(): ?Plan
    {
        return $this->plan === null ? null : PlanActivation::replaces($this->plan);
    }

    /**
     * Where the outline shows a node: its owner's outline with the node, and
     * every node above it, selected. Writes redirect here.
     */
    public static function url(Plan|WorkoutTemplate|WorkoutTemplateExercise $node): string
    {
        return route('plans.index', self::selecting($node));
    }

    /**
     * The outline open on a node's "add" form: a new workout under a plan, or
     * the exercise picker under a workout.
     */
    public static function adding(Plan|WorkoutTemplate $node): string
    {
        return route('plans.index', self::selecting($node) + ['add' => $node instanceof Plan ? 'workout' : 'exercise']);
    }

    /**
     * @return array<string, int>
     */
    private static function selecting(Plan|WorkoutTemplate|WorkoutTemplateExercise $node): array
    {
        $row = $node instanceof WorkoutTemplateExercise ? $node : null;
        $workout = $row?->workoutTemplate ?? ($node instanceof WorkoutTemplate ? $node : null);
        $plan = $workout?->plan ?? $node;

        return array_filter([
            'user' => $plan->user_id,
            'plan' => $plan->id,
            'workout' => $workout?->id,
            'row' => $row?->id,
        ]);
    }
}
