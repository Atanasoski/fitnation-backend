<?php

namespace App\Services\Exercise;

use App\Models\Exercise;
use App\Models\Partner;
use RuntimeException;

/**
 * An Exercise cannot leave a Partner Library while it is on one of that
 * Partner's Stations: a Station's Exercises always come from the library.
 */
final class ExerciseOnStation extends RuntimeException
{
    /**
     * @param  list<string>  $stations  the names of the Stations holding it
     */
    public function __construct(
        public readonly Partner $partner,
        public readonly Exercise $exercise,
        public readonly array $stations,
    ) {
        parent::__construct("{$exercise->name} is on {$partner->name}'s ".implode(', ', $stations).'; take it off first.');
    }
}
