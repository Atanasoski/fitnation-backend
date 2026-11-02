<?php

namespace App\Services\Stations;

/**
 * One Station a planner proposes: a floor-spot name and the ordered exercises
 * to curate onto it. A proposal — the admin prunes and renames in the panel;
 * nothing here is printed, so nothing here is immutable.
 */
final class ProposedStation
{
    /**
     * @param  list<int>  $exerciseIds  in display order
     */
    public function __construct(
        public readonly string $name,
        public readonly array $exerciseIds,
    ) {}
}
