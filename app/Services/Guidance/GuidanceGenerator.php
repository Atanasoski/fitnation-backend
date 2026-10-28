<?php

namespace App\Services\Guidance;

use App\Models\Exercise;

/**
 * Generates structured guidance (instructions, common mistakes) for an
 * Exercise from its catalogue data. The seam the backfill command depends on,
 * so tests can swap the AI out through the container.
 */
interface GuidanceGenerator
{
    /**
     * @throws GuidanceGenerationFailed when no valid guidance can be produced
     */
    public function generate(Exercise $exercise): ExerciseGuidance;
}
