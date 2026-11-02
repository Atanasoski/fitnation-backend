<?php

namespace App\Services\Stations;

use App\Models\Partner;
use Illuminate\Support\Collection;

/**
 * Proposes draft Stations for a Partner's gym floor from their exercise list.
 * The seam `stations:bootstrap` runs through — tests swap the planner here
 * (bound in AppServiceProvider), the same pattern as GuidanceGenerator.
 */
interface StationPlanner
{
    /**
     * Group the given exercises into gym-floor Stations.
     *
     * @param  Collection<int, \App\Models\Exercise>  $exercises  the partner's
     *                                                            not-yet-stationed, available exercises
     * @return list<ProposedStation>
     *
     * @throws StationPlanningFailed
     */
    public function plan(Partner $partner, Collection $exercises): array;
}
