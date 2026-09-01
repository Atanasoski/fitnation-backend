<?php

namespace App\Services\WorkoutSession;

use App\Enums\PersonalRecordType;

/**
 * One personal record: an exercise, what kind of best was beaten, the number
 * that stood before and the number that beat it.
 *
 * $previousBest and $newBest are read in the unit of the record's type — a
 * weight record carries kilograms, a reps record carries repetitions — so the
 * pair is only comparable within one record. Weights are in Canonical Units;
 * formatting for a Unit System belongs at the HTTP boundary (ADR-0001).
 *
 * $previousBest is always a best that actually stood: an exercise with no
 * history produces no records at all. See PersonalRecords.
 */
final readonly class PersonalRecord
{
    public function __construct(
        public int $exerciseId,
        public string $exerciseName,
        public PersonalRecordType $type,
        public int|float $previousBest,
        public int|float $newBest,
    ) {}
}
