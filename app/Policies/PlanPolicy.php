<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

/**
 * Who may manage a plan, and everything under it (workouts and their exercise
 * rows). The one rule behind every plan, workout and workout-exercise web
 * write (023, Seam 4):
 *
 * - A user's plan: a super admin for any app user, a partner admin for their
 *   own partner's members. Staff accounts (admin, partner admin) are never
 *   managed this way.
 * - A library plan (no user): a super admin, or an admin of the plan's partner.
 *
 * The plan's owner is not in that rule: the staff pages are not theirs. They
 * change their own plans through the API, which update() allows.
 */
class PlanPolicy
{
    /**
     * Create, edit, activate or delete plans belonging to $owner.
     */
    public function manageFor(User $actor, User $owner): bool
    {
        if ($owner->isStaff()) {
            return false;
        }

        return $actor->hasRole('admin') || $this->isAdminOfPartner($actor, $owner->partner_id);
    }

    /**
     * Change this plan, its workouts or their exercise rows as staff.
     */
    public function manage(User $actor, Plan $plan): bool
    {
        if ($plan->user_id === null) {
            return $actor->hasRole('admin') || $this->isAdminOfPartner($actor, $plan->partner_id);
        }

        $owner = $plan->user;

        return $owner !== null && $this->manageFor($actor, $owner);
    }

    /**
     * Change this plan by any route: its owner, or staff who may manage it.
     */
    public function update(User $actor, Plan $plan): bool
    {
        return ($plan->user_id !== null && $plan->user_id === $actor->id) || $this->manage($actor, $plan);
    }

    private function isAdminOfPartner(User $actor, ?int $partnerId): bool
    {
        return $partnerId !== null
            && $actor->partner_id === $partnerId
            && $actor->hasRole('partner_admin');
    }
}
