<?php

namespace App\Services\Guidance;

use RuntimeException;

/**
 * The generator could not produce valid guidance for an exercise: an API
 * failure, or a response that wasn't the strict JSON shape we demand. The
 * backfill command reports it and moves on to the next exercise.
 */
class GuidanceGenerationFailed extends RuntimeException {}
