<?php

namespace App\Services\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A user's plans as the outline page shows them: every Program and Routine,
 * each with its workouts and their exercise rows, and the plan a person has
 * selected (023/06).
 *
 * The selection comes from the URL, so it is only a hint: an id that is not
 * one of this user's plans (a stale link, a plan just deleted) falls back to
 * the first plan rather than failing. The tree lists active plans first.
 *
 * Loads everything it shows; callers pass the user and the requested id.
 */
final class PlanOutline
{
    /**
     * @param  Collection<int, Plan>  $plans
     */
    private function __construct(
        public readonly User $user,
        public readonly Collection $plans,
        public readonly ?Plan $plan,
    ) {}

    public static function for(User $user, ?int $planId = null): self
    {
        $plans = Plan::query()
            ->where('user_id', $user->id)
            ->with(['workoutTemplates' => fn ($query) => $query
                ->orderBy('week_number')
                ->orderByRaw('day_of_week IS NULL')
                ->orderBy('day_of_week')
                ->orderBy('order_index')
                ->orderBy('id'),
                'workoutTemplates.workoutTemplateExercises.exercise',
            ])
            ->orderByDesc('is_active')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $selected = $plans->firstWhere('id', $planId) ?? $plans->first();

        return new self($user, $plans, $selected);
    }

    /**
     * The plan activating the selected one would deactivate (ADR-0002).
     */
    public function replaces(): ?Plan
    {
        return $this->plan === null ? null : PlanActivation::replaces($this->plan);
    }
}
