<?php

namespace App\Services\Stations;

use RuntimeException;

/**
 * The planner could not produce a usable proposal: API trouble, no key, or a
 * response that failed strict validation. The command reports it and writes
 * nothing — a half-trusted plan never reaches the floor.
 */
class StationPlanningFailed extends RuntimeException {}
