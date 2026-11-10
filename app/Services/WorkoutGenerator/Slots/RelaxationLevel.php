<?php

declare(strict_types=1);

namespace App\Services\WorkoutGenerator\Slots;

/**
 * How far the Relaxation Waterfall had to loosen a Slot's role before an
 * exercise matched (GLOSSARY: Relaxation Waterfall). The integer order is
 * the waterfall order; the catalog guard asserts ceilings on it.
 */
enum RelaxationLevel: int
{
    case None = 0;
    case AngleDropped = 1;
    case PatternDropped = 2;
    case BodyweightEquivalent = 3;
    case Skipped = 4;
}
