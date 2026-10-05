<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkoutTemplate;

/**
 * A workout belongs to its plan, so who may manage it is the plan's rule:
 * see PlanPolicy. Exists so routes can guard on the bound workout directly.
 */
class WorkoutTemplatePolicy
{
    public function manage(User $actor, WorkoutTemplate $workout): bool
    {
        return $actor->can('manage', $workout->plan);
    }
}
