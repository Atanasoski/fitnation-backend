<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkoutSession;

class WorkoutSessionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, WorkoutSession $workoutSession): bool
    {
        return $user->id === $workoutSession->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, WorkoutSession $workoutSession): bool
    {
        return $user->id === $workoutSession->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, WorkoutSession $workoutSession): bool
    {
        return $user->id === $workoutSession->user_id;
    }

    /**
     * Determine whether the user can confirm the session.
     *
     * Ownership only. Whether the session is still a draft is a state rule
     * enforced by WorkoutGenerationService, which reports it as a 422 rather
     * than a 403.
     */
    public function confirm(User $user, WorkoutSession $workoutSession): bool
    {
        return $user->id === $workoutSession->user_id;
    }

    /**
     * Determine whether the user can regenerate the session.
     *
     * Ownership only, for the same reason as confirm().
     */
    public function regenerate(User $user, WorkoutSession $workoutSession): bool
    {
        return $user->id === $workoutSession->user_id;
    }
}
