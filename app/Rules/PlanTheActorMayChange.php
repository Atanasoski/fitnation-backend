<?php

namespace App\Rules;

use App\Models\Plan;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

/**
 * A plan id in a request body names a plan the signed-in user may change
 * (PlanPolicy::update): their own, or one they manage as staff. Stops a
 * workout being created in, or moved into, someone else's plan.
 */
class PlanTheActorMayChange implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $plan = Plan::find($value);

        if ($plan !== null && ! Auth::user()?->can('update', $plan)) {
            $fail('You may not change the selected plan.');
        }
    }
}
