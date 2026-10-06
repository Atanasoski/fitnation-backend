<?php

namespace App\Rules;

use App\Models\Plan;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An exercise id names one this plan's rows may use (Plan::offeredExercises):
 * from the plan owner's partner catalogue, and not archived.
 */
class ExerciseThePlanOffers implements ValidationRule
{
    public function __construct(private Plan $plan) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->plan->offeredExercises()->whereKey($value)->exists()) {
            $fail('That exercise is not in this plan\'s catalogue.');
        }
    }
}
