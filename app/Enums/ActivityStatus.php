<?php

namespace App\Enums;

/**
 * Where a user stands with training (CONTEXT.md, Activity Status). The rule
 * that assigns one lives in App\Services\Admin\ActivityStatuses.
 */
enum ActivityStatus: string
{
    case Unfinished = 'unfinished';
    case New = 'new';
    case Active = 'active';
    case Slipping = 'slipping';
    case Inactive = 'inactive';
    case Deleted = 'deleted';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
