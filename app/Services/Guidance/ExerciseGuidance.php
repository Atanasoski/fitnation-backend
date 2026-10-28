<?php

namespace App\Services\Guidance;

/**
 * Structured guidance for one Exercise: ordered step strings and common
 * mistakes, as generated for the public QR pages (spec qr-stations).
 */
readonly class ExerciseGuidance
{
    /**
     * @param  list<string>  $instructions
     * @param  list<string>  $commonMistakes
     */
    public function __construct(
        public array $instructions,
        public array $commonMistakes,
    ) {}
}
