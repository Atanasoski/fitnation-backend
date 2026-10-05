<?php

namespace App\Services\Admin;

use App\Enums\PlanType;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkoutTemplate;
use App\Services\Plan\ProgramProgress;

/**
 * The plan a user is training on, as the admin user page's strip shows it:
 * its name, its split (the workouts of the week the user is in) and, for a
 * Program, which week that is.
 *
 * A user may hold an active Program and an active Routine at once (ADR-0002).
 * The Program is the one shown — it is the structured course with a week to
 * report — and the Routine only when there is no Program.
 *
 * Loads what it needs itself.
 */
final class ActivePlan
{
    /**
     * @param  list<string>  $split  workout names, in program order
     */
    private function __construct(
        public readonly Plan $plan,
        public readonly array $split,
        public readonly ?int $week,
        public readonly ?int $weeks,
    ) {}

    public static function for(User $user): ?self
    {
        $plan = $user->activeProgram()->with('workoutTemplates')->first()
            ?? $user->activePlan()->with('workoutTemplates')->first();

        if ($plan === null) {
            return null;
        }

        $week = $plan->isProgram() ? ProgramProgress::for($plan, $user)->currentWeek() : null;

        $split = $plan->workoutTemplates
            ->when($week !== null, fn ($templates) => $templates->where('week_number', $week))
            ->sortBy([['week_number', 'asc'], ['order_index', 'asc']])
            ->map(fn (WorkoutTemplate $template) => $template->name)
            ->unique()
            ->values()
            ->all();

        return new self($plan, $split, $week, $plan->isProgram() ? $plan->duration_weeks : null);
    }

    /**
     * e.g. "Upper / Lower · week 2 of 8", or "Routine · Hips / Shoulders".
     */
    public function detail(): string
    {
        $split = implode(' / ', $this->split);

        if ($this->plan->type === PlanType::Routine) {
            return implode(' · ', array_filter(['Routine', $split]));
        }

        $week = $this->week === null ? null : 'week '.$this->week.($this->weeks ? ' of '.$this->weeks : '');

        return implode(' · ', array_filter([$split, $week]));
    }
}
