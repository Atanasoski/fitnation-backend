<?php

namespace App\Http\Middleware;

use App\Models\Plan;
use App\Models\WorkoutTemplate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The web plan, workout and workout-exercise routes are for a user's plan,
 * edited in the plan outline. A library plan (no user) has no web pages since
 * spec 025, so every such route 404s for one, before any validation. The API
 * still serves library plans.
 */
class EnsureUserPlan
{
    public function handle(Request $request, Closure $next): Response
    {
        $plan = $request->route('plan');
        $workout = $request->route('workoutTemplate');

        if (! $plan instanceof Plan && $workout instanceof WorkoutTemplate) {
            $plan = $workout->plan;
        }

        abort_if($plan instanceof Plan && $plan->user_id === null, 404);

        return $next($request);
    }
}
