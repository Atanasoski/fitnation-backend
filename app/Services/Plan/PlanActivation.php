<?php

namespace App\Services\Plan;

use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * The rule for which of a user's plans may be active at once, in one place.
 *
 * A user may hold one active plan per type: one active Program and one active
 * Routine, simultaneously. Activating a plan deactivates the user's other
 * plans of the SAME type and nothing else. App\Models\User exposes
 * activePlan() (the active Routine) and activeProgram() as two independent
 * hasOne relations, so the model layer has always assumed this; the write
 * paths did not agree with it, or with each other.
 *
 * They disagreed eight ways. Every one of them carried the comment
 * "Deactivate all other plans if this one is being set as active" over a
 * different query: some scoped to the user and every type, some to the user
 * and one type, some excluded the plan being activated and some did not.
 * The visible consequence was that creating an active Routine switched off the
 * user's Program while updating that same Routine left it alone — the same
 * user action with two outcomes depending on which verb reached the server.
 * See ADR-0002 and docs/issues/012.
 *
 * Callers pass a plan that already has its final type and owner, and get back
 * a plan that is active. That ordering matters on update paths: apply the
 * update first, then activate, so a plan that changed type is scoped against
 * its new type rather than its old one.
 *
 * Partner library plans (user_id === null) have no owner to hold a single
 * active plan for, so activate() sets is_active without deactivating anything
 * — a partner's library may offer many active plans at once.
 */
final class PlanActivation
{
    /**
     * Make this the user's active plan of its type, deactivating whichever of
     * their other plans the rule says it must.
     */
    public static function activate(Plan $plan): void
    {
        DB::transaction(function () use ($plan) {
            if ($plan->user_id !== null) {
                Plan::query()
                    ->where('user_id', $plan->user_id)
                    ->where('type', $plan->type)
                    ->whereKeyNot($plan->getKey())
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            $plan->update(['is_active' => true]);
        });
    }

    /**
     * Apply an is_active flag taken from a request: activate under the rule
     * when truthy, deactivate this plan alone when falsy, and leave it exactly
     * as it is when the flag was absent.
     *
     * The write paths all take is_active from a request, and every one of them
     * had the same branching open-coded around a bare update. Keeping it here
     * means a caller cannot implement only the activating half, which is what
     * `store` did.
     *
     * Absent must not mean false: is_active is `nullable` on every plan
     * request, so a caller renaming a plan sends no flag at all, and reading
     * that as a deactivation would switch off the plan they were editing.
     * Callers with a required flag should pass a real bool.
     *
     * Absent does not mean "skip the rule" either. `type` is required on the
     * plan update requests while is_active is not, so a request can turn an
     * active Routine into a Program without mentioning is_active at all — and
     * that plan now competes for a slot it was not holding a moment ago.
     * Re-asserting the rule for a plan that is already active costs one
     * matched-nothing UPDATE in the ordinary case and is what keeps the
     * type-change case from ending with two active Programs.
     */
    public static function apply(Plan $plan, mixed $isActive): void
    {
        if ($isActive === null) {
            if ($plan->is_active) {
                self::activate($plan);
            }

            return;
        }

        if ($isActive) {
            self::activate($plan);

            return;
        }

        $plan->update(['is_active' => false]);
    }
}
