<?php

namespace App\Enums;

/**
 * The emphasis a member chooses for their Program, selecting which Split
 * it is generated from (GLOSSARY: Split Focus, ADR-0013). A member who
 * has never chosen trains Balanced.
 */
enum SplitFocus: string
{
    case Balanced = 'balanced';
    case UpperFocus = 'upper_focus';
    case LowerFocus = 'lower_focus';

    /**
     * The emphasis as a member reads it — the same wording the apps show
     * in their Split Focus picker.
     */
    public function label(): string
    {
        return match ($this) {
            self::Balanced => 'Balanced',
            self::UpperFocus => 'Upper Body',
            self::LowerFocus => 'Lower Body',
        };
    }
}
