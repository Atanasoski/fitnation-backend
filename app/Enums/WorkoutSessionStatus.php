<?php

namespace App\Enums;

enum WorkoutSessionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Display label — the one place it is written; everything that shows a
     * status (the session's status_label, the admin filters) reads this.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'In Progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }
}
